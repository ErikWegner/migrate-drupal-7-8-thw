<?php

namespace Drupal\migratethw\Commands;

use Symfony\Component\Console\Input\InputOption;
use Drush\Commands\DrushCommands;
use Drupal\node\Entity\Node;

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
class MigrateThwCommands extends DrushCommands {

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
      $this->importNodes();
    }
    $this->logger()->notice(dt('Done'));
  }

  public function setInput(\Symfony\Component\Console\Input\InputInterface $input) {

  }

  /**
   * Validate all options
   * @param Array $options Command line options.
   *
   */
  private function validateOptions($options) {
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

  private function importNodes() {
    for ($nid = $this->first_node_id; $nid < $this->last_node_id + 1; $nid++) {
      $this->importNode($nid);
    };
  }

  private function importNode($nid) {
    $this->logger()->notice("Retrieving node {nid}", ['nid' => $nid]);
    $json = file_get_contents($this->endpointbase . 'node/' . $nid . '?api-key=' . $this->apikey);
    $data = json_decode($json);

    if ($data === NULL) {
      $this->logger()->warning("Node not found: {nid}", ['nid' => $nid]);
      return;
    }

    $this->importNodeData($data);
  }

  private function importNodeData($data) {
    $current_node_type = $data->type;
    $allowed_types = ['blog', 'date', 'forum', 'gallery_assist', 'image', 'page', 'sponsoring', 'startseitenbild', 'story'];
    if (in_array($current_node_type, $allowed_types) === FALSE) {
      $this->logger()->error('Unsupported type {type} for node {nid}', ['type' => $current_node_type, 'nid' => $data->nid]);
      return;
    }

    $node = $this->get_or_create_node($data);

    // TODO: custom node type handlings
    switch($current_node_type) {
    case 'date':
      $this->addDateFields($node, $data);
      break;
    }
    // TODO: attachments
    // TODO: images

    $node->save();
  }

  private function get_or_create_node($data) {
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
          'Language {lang} on node {nid} switched to de', ['lang' => $data->language, 'nid' => $data->nid]);
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
    $node->uid = $data->uid;

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

  private static function dateD7toD8($datestr) {
    return str_replace(" ", "T", $datestr);
  }
  
  private function addDateFields($node, $data) {
    $node->field_date->value = MigrateThwCommands::dateD7toD8($data->field_date->und[0]->value);
    $node->field_date->end_value = MigrateThwCommands::dateD7toD8($data->field_date->und[0]->value2);
  }
  
  private static function node_type_map($d7type) {
    switch ($d7type) {
    case 'gallery_assist':
    case 'image':
    case 'story':
    case 'blog':
      return 'article';
    default:
      return $d7type;
    }
  }

  /**
   * Remove p- or div-tags surrounding the summary
   * @param string $summary Imported summary
   * @return string Cleaned summary
   */
  private static function clean_summary($rawsummary) {
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
  private static function format_map($format) {
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

  private function fix_node_summary($node) {
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
