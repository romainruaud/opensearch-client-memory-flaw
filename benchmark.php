<?php

require 'vendor/autoload.php';

use OpenSearch\ClientBuilder;
use GuzzleHttp\Promise;

echo "Creating Opensearch client " . \OpenSearch\Client::VERSION . "\n";

$maxParallelHandles = 10;

$clientBuilder = ClientBuilder::create();
$clientBuilder->setHosts(['http://opensearch:9200']);

$handlerParams = ['max_handles' => (int) $maxParallelHandles];
$handler = ClientBuilder::defaultHandler($handlerParams);
$clientBuilder->setHandler($handler);

$client = $clientBuilder->build();

$indexName = uniqid('benchmark_index');

echo "Creating index " . $indexName . "\n";

if (!$client->indices()->exists(['index' => $indexName])) {
    $client->indices()->create(['index' => $indexName]);
}

$batchSize = 1000;
$totalBatches = 100;
$totalDocs = $batchSize * $totalBatches;

echo "Creating $totalDocs documents with async mode, batch size of $batchSize \n";

$start = microtime(true);

$futureBulks = [];

for ($cpt = 0; $cpt < $totalDocs; $cpt++) {
    $bulkParams['body'][] = [
        'index' => [
            '_index' => $indexName,
        ]
    ];

    for ($j = 1; $j <= 10; $j++) {
        $document["int_field_$j"] = rand(1, 10000);
    }

    for ($j = 1; $j <= 10; $j++) {
        $document["text_field_$j"] = 'texte_' . bin2hex(random_bytes(5));
    }

    $bulkParams['body'][] = $document;

    if ($cpt % $batchSize === 0) {

        // Use future mode of the client.
        // Will stack all bulk operations and execute them later with only one curl_multi_exec call in parallel mode.
        $bulkParams['client'] = ['future' => 'lazy'];

        // This is not executed in real time but put into a future bulk queue.
        $futureBulks[] = $client->bulk($bulkParams);
        echo ".";

        $bulkParams = ['body' => []];
    }

    if (count($futureBulks) === $maxParallelHandles) {
        echo "X\n";
        // Iterating on future response
        // and accessing properties like 'items' or 'error' will cause the queue to process.
        // It's like manually resolving by calling $futureBulks[sizeof($futureBulks) - 1]->wait(); .

        /** @var \GuzzleHttp\Ring\Future\FutureArray $futureBulkResponse */
        foreach ($futureBulks as $futureBulkResponse) {
            $resolvedResponse = [
                'items'  => $futureBulkResponse['items'],  // Implicit resolution of the promise.
                'errors' => $futureBulkResponse['errors'], // Implicit resolution of the promise.
            ];
        }

        $futureBulks = [];
    }
}

$end = microtime(true);
$duration = $end - $start;

echo "Total time : " . round($duration, 3) . " seconds\n";

$client->indices()->refresh(['index' => $indexName]);
$stats = $client->indices()->stats(['index' => $indexName]);

echo "$indexName contains " . $stats['indices'][$indexName]['total']['docs']['count'] . " documents.";
