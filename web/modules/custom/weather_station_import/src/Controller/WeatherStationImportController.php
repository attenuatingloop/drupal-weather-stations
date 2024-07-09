<?php

namespace Drupal\weather_station_import\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class WeatherStationImportController extends ControllerBase {
  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * WeatherStationImportController constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory')->get('weather_station_import')
    );
  }

  /**
   * Imports weather stations from the Weather API.
   */
  public function importStations() {
    $client = new Client();
    $weather_url = 'https://api.weather.gov/stations';

    try {
      $response = $client->get($weather_url);
      $data = json_decode($response->getBody(), TRUE);

      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \Exception('Error decoding JSON response: ' . json_last_error_msg());
      }

      foreach ($data['features'] as $station) {
        $properties = $station['properties'];
        $coordinates = $station['geometry']['coordinates'];

        // Validate the necessary fields are available.
        if (empty($properties['stationIdentifier']) || empty($properties['name']) || !isset($coordinates[0]) || !isset($coordinates[1])) {
          $this->logger->error('Missing necessary data for station: @station', ['@station' => print_r($station, TRUE)]);
          continue;
        }

        // Check if a node with the same Station Identifier already exists.
        $query = \Drupal::entityQuery('node')
          ->accessCheck(TRUE)
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

    } catch (RequestException $e) {
      $this->logger->error('HTTP request failed: @message', ['@message' => $e->getMessage()]);
      return [
        '#type' => 'markup',
        '#markup' => $this->t('Failed to import weather stations. Please try again later.'),
      ];

    } catch (\Exception $e) {
      $this->logger->error('An error occurred: @message', ['@message' => $e->getMessage()]);
      return [
        '#type' => 'markup',
        '#markup' => $this->t('An error occurred while importing weather stations. Please check the logs for more details.'),
      ];
    }
  }
}