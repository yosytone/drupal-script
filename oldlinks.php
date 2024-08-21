<?php

$INIT_TIME = microtime(1);

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;
use Drupal\node\Entity\Node;
use Drupal\Core\Database\Database;

chdir('web');
$autoloader = require_once 'autoload.php';
$kernel = new DrupalKernel('prod', $autoloader);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);

processAllArticles();

$kernel->terminate($request, $response);

function processAllArticles() {
    $connection = \Drupal\Core\Database\Database::getConnection();
    $query = $connection->select('node_field_data', 'nfd')
        ->distinct(TRUE);
    $query->fields('nfd', ['nid']);
    $query->condition('nfd.type', ['article', 'news'], 'IN');

    $results = $query->execute()->fetchAll();
    $nids = array_column($results, 'nid');

    echo "Processing " . count($nids) . " articles...\n";

    $film_map = loadAllFilms();
    $person_map = loadAllPeople();

    foreach ($nids as $nid) {
        echo $nid ."\n";
        $updated_body = processArticle($nid, $film_map, $person_map);
        if ($updated_body !== null) {
            //saveUpdatedArticle($nid, $updated_body);
            echo "Article with nid $nid updated.\n";
        }
    }

    echo "All articles processed.\n";
}

function loadAllFilms() {
    $films = [];
    $connection = \Drupal\Core\Database\Database::getConnection();
    $query = $connection->select('node__field_film_old_km_id', 'film')
        ->fields('film', ['entity_id', 'field_film_old_km_id_value']);
    $result = $query->execute();

    foreach ($result as $record) {
        $films[$record->field_film_old_km_id_value] = $record->entity_id;
    }

    return $films;
}

function loadAllPeople() {
    $people = [];
    $connection = \Drupal\Core\Database\Database::getConnection();
    $query = $connection->select('node__field_person_old_km_id', 'person')
        ->fields('person', ['entity_id', 'field_person_old_km_id_value']);
    $result = $query->execute();

    foreach ($result as $record) {
        $people[$record->field_person_old_km_id_value] = $record->entity_id;
    }

    return $people;
}

function processArticle($nid, $film_map, $person_map) {
    $node = \Drupal\node\Entity\Node::load($nid);
    if (!$node) return null;

    $body = $node->get('body')->value;

    $body = preg_replace_callback('/<a href="https:\/\/www\.kinomania\.ru\/film\/(\d+)\/?">.*?<\/a>/', function ($matches) use ($film_map) {
        $old_id = $matches[1];
        if (isset($film_map[$old_id])) {
            $new_id = $film_map[$old_id];
            return '<film-out nid="' . $new_id . '"></film-out>';
        }
        return $matches[0];
    }, $body);

    $body = preg_replace_callback('/<a href="https:\/\/www\.kinomania\.ru\/people\/(\d+)\/?">.*?<\/a>/', function ($matches) use ($person_map) {
        $old_id = $matches[1];
        if (isset($person_map[$old_id])) {
            $new_id = $person_map[$old_id];
            return '<person-out nid="' . $new_id . '"></person-out>';
        }
        return $matches[0];
    }, $body);

    echo $body;
    return $body;
}

function saveUpdatedArticle($nid, $updated_body) {
    $node = \Drupal\node\Entity\Node::load($nid);
    if ($node) {
        $node->set('body', ['value' => $updated_body, 'format' => 'full_html']);
        $node->save();
        echo "Article with nid $nid updated.\n";
    }
}
