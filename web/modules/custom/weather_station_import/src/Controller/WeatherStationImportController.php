<?php

namespace Drupal\weather_station_import\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use GuzzleHttp\Client;

class WeatherStationImportController extends ControllerBase {

  public function importStations() {
    $client = new Client();
    $response = $client->get('https://api.weather.gov/stations');
    $data = json_decode($response->getBody(), TRUE);

    foreach ($data['features'] as $station) {
      $properties = $station['properties'];
      $coordinates = $station['geometry']['coordinates'];

      // Check if a node with the same Station Identifier already exists.
      $query = \Drupal::entityQuery('node')
        ->condition('type', 'weather_station')
        ->condition('field_station_identifier', $properties['stationIdentifier']);
      $nids = $query->execute();

      if (!empty($nids)) {
        // Node exists, update the existing node.
        $node = Node::load(reset($nids));
        $node->setTitle($properties['name']);
        $node->set('field_gps_coordinates', [
          'lat' => $coordinates[1],
          'lng' => $coordinates[0],
        ]);
        $node->set('field_altitude', $properties['elevation']['value']);
      } else {
        // Node does not exist, create a new one.
        $node = Node::create([
          'type' => 'weather_station',
          'title' => $properties['name'],
          'field_station_identifier' => $properties['stationIdentifier'],
          'field_gps_coordinates' => [
            'lat' => $coordinates[1],
            'lng' => $coordinates[0],
          ],
          'field_altitude' => $properties['elevation']['value'],
        ]);
      }

      $node->save();
    }

    return [
      '#type' => 'markup',
      '#markup' => $this->t('Weather stations imported/reimported successfully.'),
    ];
  }
}