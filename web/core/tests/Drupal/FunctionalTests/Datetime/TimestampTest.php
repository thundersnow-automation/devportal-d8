<?php

namespace Drupal\FunctionalTests\Datetime;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the functionality of Timestamp core field UI.
 *
 * @group field
 */
class TimestampTest extends BrowserTestBase {

  /**
   * An array of display options to pass to entity_get_display().
   *
   * @var array
   */
  protected $displayOptions;

  /**
   * A field storage to use in this test class.
   *
   * @var \Drupal\field\Entity\FieldStorageConfig
   */
  protected $fieldStorage;

  /**
   * The field used in this test class.
   *
   * @var \Drupal\field\Entity\FieldConfig
   */
  protected $field;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node', 'entity_test', 'field_ui'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $web_user = $this->drupalCreateUser([
      'access content',
      'view test entity',
      'administer entity_test content',
      'administer entity_test form display',
      'administer entity_test display',
      'administer content types',
      'administer node fields',
    ]);

    $this->drupalLogin($web_user);
    $field_name = 'field_timestamp';
    $type = 'timestamp';
    $widget_type = 'datetime_timestamp';
    $formatter_type = 'timestamp';

    $this->fieldStorage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'type' => $type,
    ]);
    $this->fieldStorage->save();
    $this->field = FieldConfig::create([
      'field_storage' => $this->fieldStorage,
      'bundle' => 'entity_test',
      'required' => TRUE,
    ]);
    $this->field->save();

    EntityFormDisplay::load('entity_test.entity_test.default')
      ->setComponent($field_name, ['type' => $widget_type])
      ->save();

    $this->displayOptions = [
      'type' => $formatter_type,
      'label' => 'hidden',
    ];

    EntityViewDisplay::create([
      'targetEntityType' => $this->field->getTargetEntityTypeId(),
      'bundle' => $this->field->getTargetBundle(),
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent($field_name, $this->displayOptions)
      ->save();
  }

  /**
   * Tests the "datetime_timestamp" widget.
   */
  public function testWidget() {
    // Build up a date in the UTC timezone.
    $value = '2012-12-31 00:00:00';
    $date = new DrupalDateTime($value, 'UTC');

    // Update the timezone to the system default.
    $date->setTimezone(timezone_open(drupal_get_user_timezone()));

    // Display creation form.
    $this->drupalGet('entity_test/add');

    // Make sure the "datetime_timestamp" widget is on the page.
    $fields = $this->xpath('//div[contains(@class, "field--widget-datetime-timestamp") and @id="edit-field-timestamp-wrapper"]');
    $this->assertEquals(1, count($fields));

    // Look for the widget elements and make sure they are empty.
    $this->assertSession()->fieldExists('field_timestamp[0][value][date]');
    $this->assertSession()->fieldValueEquals('field_timestamp[0][value][date]', '');
    $this->assertSession()->fieldExists('field_timestamp[0][value][time]');
    $this->assertSession()->fieldValueEquals('field_timestamp[0][value][time]', '');

    // Submit the date.
    $date_format = DateFormat::load('html_date')->getPattern();
    $time_format = DateFormat::load('html_time')->getPattern();

    $edit = [
      'field_timestamp[0][value][date]' => $date->format($date_format),
      'field_timestamp[0][value][time]' => $date->format($time_format),
    ];
    $this->drupalPostForm(NULL, $edit, 'Save');

    // Make sure the submitted date is set as the default in the widget.
    $this->assertSession()->fieldExists('field_timestamp[0][value][date]');
    $this->assertSession()->fieldValueEquals('field_timestamp[0][value][date]', $date->format($date_format));
    $this->assertSession()->fieldExists('field_timestamp[0][value][time]');
    $this->assertSession()->fieldValueEquals('field_timestamp[0][value][time]', $date->format($time_format));

    // Make sure the entity was saved.
    preg_match('|entity_test/manage/(\d+)|', $this->getSession()->getCurrentUrl(), $match);
    $id = $match[1];
    $this->assertSession()->pageTextContains(sprintf('entity_test %s has been created.', $id));

    // Make sure the timestamp is output properly with the default formatter.
    $medium = DateFormat::load('medium')->getPattern();
    $this->drupalGet('entity_test/' . $id);
    $this->assertSession()->pageTextContains($date->format($medium));

    // Set the 'timestamp_ago' formatter using the UI.
    $edit = ['fields[field_timestamp][type]' => 'timestamp_ago'];
    $this->drupalPostForm('entity_test/structure/entity_test/display/default', $edit, 'Save');
    // Reconfigure the field formatter 'timestamp_ago'.
    $this->drupalPostForm(NULL, [], 'field_timestamp_settings_edit');
    $edit = [
      'fields[field_timestamp][settings_edit_form][settings][future_format]' => 'ends in @interval',
      'fields[field_timestamp][settings_edit_form][settings][past_format]' => 'started @interval ago',
      'fields[field_timestamp][settings_edit_form][settings][granularity]' => 3,
    ];
    $this->drupalPostForm(NULL, $edit, 'Update');
    $this->drupalPostForm(NULL, [], 'Save');

    // Check that values were saved correctly in the backend.
    $settings = EntityViewDisplay::load('entity_test.entity_test.default')
      ->getComponent('field_timestamp')['settings'];
    $this->assertEquals('ends in @interval', $settings['future_format']);
    $this->assertEquals('started @interval ago', $settings['past_format']);
    $this->assertEquals(3, $settings['granularity']);

    /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
    $date_formatter = \Drupal::service('date.formatter');
    $now = \Drupal::time()->getRequestTime();
    $past = $now - (60 * 60 * 24 * 650);
    $future = $now + (60 * 60 * 24 * 750);
    foreach ([$past => 'started %s ago', $future => 'ends in %s'] as $timestamp => $format) {
      EntityTest::load($id)->set('field_timestamp', $timestamp)->save();
      $this->drupalGet('entity_test/' . $id);
      // We use the time from request to avoid any difference.
      $request_time = strtotime($this->getSession()->getResponseHeaders()['Date'][0]);
      $to = max($timestamp, $request_time);
      $from = min($timestamp, $request_time);
      $interval = $date_formatter->formatDiff($from, $to, ['granularity' => 3]);
      $this->assertSession()->pageTextContains(sprintf($format, $interval));
    }
  }

}
