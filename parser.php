<?php

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Database\Database;

chdir('web');
$autoloader = require_once 'autoload.php';
$kernel = new DrupalKernel('prod', $autoloader);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);

$IMDB = new IMDB('https://www.imdb.com/title/tt7631058');

if ($IMDB->isReady) {
    $data = $IMDB->getAll();

    if (isset($data['getCastAndCharacterAsUrl']['value'])) {
        $cast = $data['getCastAndCharacterAsUrl']['value'];
        $actors = explode(" / ", $cast);
        $person_imdb_ids = [];
        foreach ($actors as $actor) {
            if (preg_match('/nm(\d+)/', $actor, $matches)) {
                $person_imdb_id = ltrim($matches[1], '0');
                $person_imdb_ids[] = $person_imdb_id;
            }
        }

        $person_mania_ids = get_person_node_ids_by_imdb_ids($person_imdb_ids);
        /////////////////////
        foreach ($person_mania_ids as $actor) {
            echo "$actor.\n";
        }
        ////////////////////

    } else {
        echo "No cast information available.\n";
    }
} else {
    echo "IMDB API is not ready.\n";
}

$kernel->terminate($request, $response);

function get_person_node_ids_by_imdb_ids(array $imdb_ids) {
    $database = Database::getConnection();
    $result = [];
    
    try {
        $query = $database->select('node__field_person_imdb_id', 'np')
            ->fields('np', ['field_person_imdb_id_value', 'entity_id'])
            ->condition('np.field_person_imdb_id_value', $imdb_ids, 'IN')
            ->execute();

        foreach ($query as $record) {
            $imdb_id = $record->field_person_imdb_id_value;
            $result[] = $record->entity_id;
        }
        
    } catch (\Exception $e) {
        echo "Database query failed: " . $e->getMessage() . "\n";
    }
    
    return $result;
}
