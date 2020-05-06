<?php

namespace Drupal\migratethw\Commands;

use Symfony\Component\Console\Input\InputOption;
use Drush\Commands\DrushCommands;
use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\File\FileSystemInterface;

/**
 * Changes:
 * 
 * Inhaltstyp Article:
 *   Feld field_images als Bilder
 *   Feld field_attachments als Anhänge publicfiles
 * 
 * Inhaltstyp Forum:
 *   Feld field_attachments als Anhänge privatefiles
 */

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class MigrateThwCommands extends DrushCommands
{

  private $endpointbase = '';
  private $apikey = '';
  private $first_node_id = 0;
  private $last_node_id = 0;
  private $pathbase = '';
  private $delete_existing_nodes = TRUE;

  /**
   * Run migration.
   *
   * @options endpointbase Base URI of exporting site.
   * @options apikey Authentication key
   *
   * @command migratethw:run
   */
  public function commandName(
    $options = [
      'endpointbase' => InputOption::VALUE_REQUIRED,
      'apikey' => InputOption::VALUE_REQUIRED,
      'pathbase' => 'https://www.thw-bernburg.de',
      'first_node_id' => 1,
      'last_node_id' => 774,
      'delete_existing_nodes' => FALSE,
    ]
  ) {
    $this->logger()->notice(dt('Start'));
    if ($this->validateOptions($options)) {
      //$this->importTaxonomies();
      $this->importNodes();
    }
    $this->logger()->notice(dt('Done'));
  }

  public function setInput(\Symfony\Component\Console\Input\InputInterface $input)
  {
  }

  /**
   * Validate all options
   * @param Array $options Command line options.
   *
   */
  private function validateOptions($options)
  {
    $this->logger()->debug('validating options');
    $optionnames = ['endpointbase', 'apikey'];
    foreach ($optionnames as $optionname) {
      if (empty($options[$optionname])) {
        $this->logger()->error('Option missing: {optionname}', ['optionname' => $optionname]);
        return FALSE;
      }
      $this->$optionname = $options[$optionname];
    }

    $this->first_node_id = intval($options['first_node_id']);
    $this->last_node_id = intval($options['last_node_id']);
    $this->pathbase = $options['pathbase'];
    $this->delete_existing_nodes = $options['delete_existing_nodes'];

    return TRUE;
  }

  private function importTaxonomies()
  {
    $this->logger()->notice("Migrating taxonomies");
    $vocabulary_mapping = [
      '1' => 'forums',
      '2' => 'bereich',
      '3' => 'terminart',
    ];
    foreach ($vocabulary_mapping as $vid => $vocabulary) {
      // https://drupal.stackexchange.com/a/213257
      /*$tids = \Drupal::entityQuery('taxonomy_term')
        ->condition('vid', $vocabulary)
        ->execute();

      $controller = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
      $entities = $controller->loadMultiple($tids);
      $controller->delete($entities);*/

      $tids = \Drupal::entityQuery('taxonomy_term')
        ->condition('vid', $vocabulary)
        ->execute();
      $this->logger()->notice('Deleting from {vocabulary}', ['vocabulary' => $vocabulary]);
      entity_delete_multiple('taxonomy_term', $tids);
    }

    for ($page = 0; $page < 2; $page++) {
      $this->logger()->notice("Loading taxonomy terms from page {page}", ['page' => $page]);
      $json = file_get_contents($this->endpointbase . 'taxonomy_term?api-key=' . $this->apikey . '&page=' . $page);
      $data = json_decode($json);

      foreach ($data as $termdata) {
        $this->logger()->notice("Term {tname} for {v} (parent: {p}", [
          'tname' => $termdata->name,
          'v' => $vocabulary_mapping[$termdata->vid],
          'p' => $termdata->parent,
        ]);
        $term = Term::create([
          'tid' => $termdata->tid,
          'name' => $termdata->name,
          'description' => $termdata->description,
          'vid' => $vocabulary_mapping[$termdata->vid],
          'parent' => ['target_id' => $termdata->parent],
        ]);
        $term->save();
      }
    }
  }

  private function importNodes()
  {
    for ($nid = $this->first_node_id; $nid < $this->last_node_id + 1; $nid++) {
      $this->importNode($nid);
    };
  }

  private function importNode($nid)
  {
    $this->logger()->notice("Retrieving node {nid}", ['nid' => $nid]);
    $json = file_get_contents($this->endpointbase . 'node/' . $nid . '?api-key=' . $this->apikey);
    $data = json_decode($json);

    if ($data === NULL) {
      $this->logger()->warning("Node not found: {nid}", ['nid' => $nid]);
      return;
    }

    $this->importNodeData($data);
  }

  private function importNodeData($data)
  {
    $current_node_type = $data->type;
    $allowed_types = ['blog', 'date', 'forum', 'page', 'sponsoring', 'story'];
    if (in_array($current_node_type, $allowed_types) === FALSE) {
      $this->logger()->error('Unsupported type {type} for node {nid}', ['type' => $current_node_type, 'nid' => $data->nid]);
      return;
    }

    $node = $this->get_or_create_node($data);

    // TODO: custom node type handlings
    switch ($current_node_type) {
      case 'blog':
      case 'article':
        $node->comment->status = CommentItemInterface::CLOSED;
        $node->field_area->target_id = $data->taxonomy_vocabulary_2->und[0]->tid;
        break;
      case 'date':
        $this->addDateFields($node, $data);
        break;
      case 'sponsoring':
        $node->set('field_unternehmen', $data->field_unternehmen->und[0]->value);
        $node->set('field_sponsoringart', $data->field_sponsoringart->und[0]->value);
        $node->set('field_url', $data->field_url->und[0]->value);
        break;
      case 'forum':
        $node->set('taxonomy_forums', $data->taxonomy_forums->und[0]->tid);
        break;
    }

    // attachments
    if (isset($data->upload->und)) {
      $public_attachments = $current_node_type != 'forum';
      $node->set(
        $public_attachments ? 'field_publicfiles' : 'field_privatefiles',
        $this->migrateFiles($data->upload->und, $public_attachments));
    }

    // Migrate images
    if (isset($data->field_img->und)) {
      $node->set('field_images', $this->migrateFiles($data->field_img->und, true));
    }

    $node->save();
  }

  private function get_or_create_node($data)
  {
    $node = Node::load($data->nid);
    /* Remove existing nodes if configured */
    if ($node !== NULL && $this->delete_existing_nodes) {
      $node->delete();
      $node = NULL;
    }

    // Url alias from source system
    $nodealias = urldecode(substr($data->path, strlen($this->pathbase)));

    if ($node === NULL) {
      // Select language from source node
      if (!($data->language === "de" || $data->language === "en" || $data->language === "und")) {
        $this->logger()->warning(
          'Language {lang} on node {nid} switched to de',
          ['lang' => $data->language, 'nid' => $data->nid]
        );
        $data->language = "de";
      }

      $node = Node::create([
        'nid' => $data->nid,
        'type' => MigrateThwCommands::node_type_map($data->type),
        'langcode' => $data->language,
        'path' => $nodealias,
      ]);
    }

    // Extract body field from source node
    $body = $data->body->und[0];

    // Set or update fields
    $node->uid = MigrateThwCommands::uid_map($data->uid);
    $node->title->value = $data->title;
    $node->status->value = $data->status;
    $node->created->value = $data->created;
    $node->changed->value = $data->changed;
    $node->body->value = $body->value;
    $node->body->summary = MigrateThwCommands::clean_summary($body->summary);
    $node->body->format = MigrateThwCommands::format_map($body->format);

    // Fix summary field
    $this->fix_node_summary($node);

    return $node;
  }

  private static function dateD7toD8($datestr)
  {
    return str_replace(" ", "T", $datestr);
  }

  private function addDateFields($node, $data)
  {
    $node->field_date_start->value = MigrateThwCommands::dateD7toD8($data->field_date->und[0]->value);
    $node->field_date_end->value = MigrateThwCommands::dateD7toD8($data->field_date->und[0]->value2);
  }

  private function migrateFiles($list, $schemePublic)
  {
    $r = [];
    foreach ($list as $fileref) {
      $this->logger()->notice('Migrating {filename} ({fid})', ['filename' => $fileref->filename, 'fid' => $fileref->fid]);
      $json = json_decode(file_get_contents($this->endpointbase . 'file/' . $fileref->fid . '?api-key=' . $this->apikey));
      $file_data = base64_decode($json->file);
      $path = ($schemePublic ? 'public://' : 'private://') . date('Y-m', $json->timestamp);
      \Drupal::service('file_system')->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY);
      $file = file_save_data($file_data, $path . '/' . $json->filename, FileSystemInterface::EXISTS_RENAME);
      $attachmentdata = [
        'target_id' => $file->id(),
        'status' => $fileref->status,
      ];
      if (isset($fileref->display)) {
        $attachmentdata['display'] = $fileref->display;
      }
      if (isset($fileref->description)) {
        $attachmentdata['description'] = $fileref->description;
      }
      if (isset($fileref->alt)) {
        $attachmentdata['alt'] = $fileref->alt;
      }
      if (isset($fileref->title)) {
        $attachmentdata['alt'] = $fileref->title;
      }
      
      $r[] = $attachmentdata;
    }

    return $r;
  }

  private static function uid_map($d7_userid)
  {
    return $d7_userid == 6 ? 5 : 4;
  }

  private static function node_type_map($d7type)
  {
    switch ($d7type) {
      case 'story':
      case 'blog':
        return 'article';
      default:
        /* page, forum, date, sponsoring */
        return $d7type;
    }
  }

  /**
   * Remove p- or div-tags surrounding the summary
   * @param string $summary Imported summary
   * @return string Cleaned summary
   */
  private static function clean_summary($rawsummary)
  {
    if ($rawsummary == null) {
      return null;
    }
    $summary = trim($rawsummary);
    if (substr($summary, 0, 3) === '<p>' && substr($summary, -4) === '</p>') {
      return substr($summary, 3, -4);
    }
    if (substr($summary, 0, 5) === '<div>' && substr($summary, -6) === '</div>') {
      return substr($summary, 5, -6);
    }

    return $summary;
  }

  /**
   *  Map old input format to new Drupal 8 input format
   */
  private static function format_map($format)
  {
    $format_map = array(
      1 => 'basic_html', // Filtered HTML in D7.
      2 => 'full_html', // Full HTML in D7.
      3 => 'restricted_html', // Comments
      4 => 'plain_text', // Plain Text in D7.
      'php_code' => '',
    );

    if (array_key_exists($format, $format_map)) {
      return $format_map[$format];
    }

    return $format_map[3];
  }

  private function fix_node_summary($node)
  {
    $v = $node->body->value;

    $needle = '<!--break-->';
    $pos = stripos($v, $needle);

    if ($pos === FALSE) {
      return;
    }

    if ($node->body->summary != "") {
      $this->logger()->error("Summary with --break-- in node {nid}", ['nid' => $node->id]);
      return;
    }

    $summary = substr($v, 0, $pos);
    if (substr($summary, 0, 3) === '<p>') {
      $summary = substr($summary, 3);
    }
    if (substr($summary, 0, 5) === '<div>') {
      $summary = substr($summary, 5);
    }

    $body = str_ireplace($needle, '', $v);

    $node->body->summary = $summary;
    $node->body->value = $body;
  }
}
