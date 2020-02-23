# migrate-drupal-7-8-thw
Migration from Drupal 7 to Drupal 8

## Blockers

NONE

## Modules on Drupal 7

* services
* services_api_key_auth

## Pending questions

* Calendar content type:
  * All day events vs. events with start and end time
* Calendar module
  * https://www.drupal.org/project/fullcalendar_view
* Image upload

## To do (before import)

* Create content types
  * -sponsoring-
  * -front page image (as image entity)-
* Modules:
    * acl
    * forums
    * calendar
    * fullcalendar_view
* Set permissions
  * Disable forum content type for all users

## Run migration

    cd /srv/http/drupal/web
    ../vendor/bin/drush --uri http://thw8.localhost migratethw:run

## To do (after import)

* Create front page
  * Random image from front page image pool
  * Recent items
  * Weather warnings
  * Calendar
* Blocks
* Footer menu
* Aggregator

## To implement

* -Migrate taxonomies-
* -Migrate forum categories-
* Migrate nodes
  * -pages-
  * -articles-
  * -sponsoring-
  * -front page image-
  * -blogs-
    * -Images with-
      * -cropping data-
      * -alt-text-
  * -calendar dates-
    * -Leitungssitzung: Ende leer? Ende = Anfang + 90 Min-
  * -forum messages-
