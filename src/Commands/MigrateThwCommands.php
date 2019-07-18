<?php

namespace Drupal\migratethw\Commands;

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
     * @command migratethw:run
     */
    public function commandName() {
        $this->logger()->notice(dt('Start'));

        $this->logger()->notice(dt('Done'));
    }

    public function setInput(\Symfony\Component\Console\Input\InputInterface $input) {
        
    }

}
