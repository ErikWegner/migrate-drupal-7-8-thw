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
    ]
  ) {
    $this->logger()->notice(dt('Start'));
    if ($this->validateOptions($options)) {
      // implementaion goes here
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
    }

    return TRUE;
  }

}
