<?php

namespace Drupal\migratethw\Commands;

use Symfony\Component\Console\Input\InputOption;
use Drush\Commands\DrushCommands;

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
    // TODO: implement
  }
}
