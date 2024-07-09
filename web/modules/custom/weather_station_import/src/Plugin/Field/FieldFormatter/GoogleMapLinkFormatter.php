<?php

namespace Drupal\weather_station_import\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Plugin implementation of the 'google_map_link' formatter.
 *
 * @FieldFormatter(
 *   id = "google_map_link",
 *   label = @Translation("Google Maps Link"),
 *   field_types = {
 *     "geolocation"
 *   }
 * )
 */
class GoogleMapLinkFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      if (!empty($item->lat) && !empty($item->lng)) {
        $url = Url::fromUri("https://www.google.com/maps?q={$item->lat},{$item->lng}", ['attributes' => ['target' => '_blank']]);
        $elements[$delta] = [
          '#type' => 'link',
          '#title' => $this->t("{$item->lat}, {$item->lng}"),
          '#url' => $url,
        ];
      }
    }

    return $elements;
  }
}