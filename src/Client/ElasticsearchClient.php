<?php

namespace rajmundtoth0\AuditDriver\Client;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Elastic\Transport\Exception\NoNodeAvailableException;
use Http\Promise\Promise;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use rajmundtoth0\AuditDriver\Exceptions\AuditDriverException;
use rajmundtoth0\AuditDriver\Exceptions\AuditDriverMissingCaCertException;
use rajmundtoth0\AuditDriver\Models\DocumentModel;
use rajmundtoth0\AuditDriver\Models\MappingModel;
use RuntimeException;

class ElasticsearchClient
{
    private bool|string $caBundlePath = false;

    private ClientBuilder $clientBuilder;

    private Client $client;

    /**
     * @throws AuditDriverMissingCaCertException
     */
    public function setClient(?Client $client = null): self
    {
        $this->setHosts();
        $this->setBasicAuth();
        $this->setCaBundle();

        if (!$client) {
            $client = $this
                ->clientBuilder
                ->build();
        }

        $this->client = $client;

        return $this;
    }

    /**
     * @throws AuditDriverMissingCaCertException
     */
    public function setCaBundle(): void
    {
        if (!Config::boolean('audit.drivers.elastic.useCaCert', false)) {
            return;
        }
        $caCert = Config::string('audit.drivers.elastic.certPath', '');

        $this->caBundlePath = Storage::path($caCert);
        if (!$this->caBundlePath) {
            throw new AuditDriverMissingCaCertException(
                message: 'Cacert file path is invalid!',
            );
        }

        $this->clientBuilder->setCABundle(
            cert: $this->caBundlePath,
        );
    }

    public function setBasicAuth(): void
    {
        if (!Config::boolean('audit.drivers.elastic.useBasicAuth', false)) {
            return;
        }
        $userName = Config::string('audit.drivers.elastic.userName', '');
        $password = Config::string('audit.drivers.elastic.password', '');

        $this->clientBuilder->setBasicAuthentication(
            username: $userName,
            password: $password,
        );
    }

    public function setHosts(): void
    {
        $hosts               = Config::array('audit.drivers.elastic.hosts', ['http://localhost:9200']);
        $this->clientBuilder = ClientBuilder::create()
            ->setHosts($hosts);
    }

    /**
     * @throws ClientResponseException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function updateAliases(string $index): Elasticsearch|Promise
    {
        $params         = [];
        $params['body'] = [
            'actions' => [
                [
                    'add' => [
                        'index' => $index,
                        'alias' => $index.'_write',
                    ],
                ],
            ],
        ];

        return $this->client->indices()->updateAliases($params);
    }

    /**
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     * @throws RuntimeException
     */
    public function createIndex(
        string $index,
        string $auditType,
        int $shardNumber = 5,
        int $replicaNumber = 0
    ): Elasticsearch|Promise {
        $mappings = new MappingModel();
        $params   = [
            'index' => $index,
            'type'  => $auditType,
            'body'  => [
                'settings' => [
                    'number_of_shards'   => $shardNumber,
                    'number_of_replicas' => $replicaNumber,
                ],
                'mappings' => [
                    'properties' => $mappings->getModel(),
                ],
            ],
        ];

        return $this->client->indices()->create($params);
    }

    /**
     * @param array{
     *     index?: string|array<string>, // A comma-separated list of index names to search; use `_all` or empty string to perform the operation on all indices
     *     analyzer?: string, // The analyzer to use for the query string
     *     analyze_wildcard?: bool, // Specify whether wildcard and prefix queries should be analyzed (default: false)
     *     ccs_minimize_roundtrips?: bool, // Indicates whether network round-trips should be minimized as part of cross-cluster search requests execution
     *     default_operator?: string, // The default operator for query string query (AND or OR)
     *     df?: string, // The field to use as default where no field prefix is given in the query string
     *     explain?: bool, // Specify whether to return detailed information about score computation as part of a hit
     *     stored_fields?: string|array<string>, // A comma-separated list of stored fields to return as part of a hit
     *     docvalue_fields?: string|array<string>, // A comma-separated list of fields to return as the docvalue representation of a field for each hit
     *     from?: int, // Starting offset (default: 0)
     *     force_synthetic_source?: bool, // Should this request force synthetic _source? Use this to test if the mapping supports synthetic _source and to get a sense of the worst case performance. Fetches with this enabled will be slower the enabling synthetic source natively in the index.
     *     ignore_unavailable?: bool, // Whether specified concrete indices should be ignored when unavailable (missing or closed)
     *     ignore_throttled?: bool, // Whether specified concrete, expanded or aliased indices should be ignored when throttled
     *     allow_no_indices?: bool, // Whether to ignore if a wildcard indices expression resolves into no concrete indices. (This includes `_all` string or when no indices have been specified)
     *     expand_wildcards?: string, // Whether to expand wildcard expression to concrete indices that are open, closed or both.
     *     lenient?: bool, // Specify whether format-based query failures (such as providing text to a numeric field) should be ignored
     *     preference?: string, // Specify the node or shard the operation should be performed on (default: random)
     *     q?: string, // Query in the Lucene query string syntax
     *     routing?: string|array<string>, // A comma-separated list of specific routing values
     *     scroll?: int|string, // Specify how long a consistent view of the index should be maintained for scrolled search
     *     search_type?: string, // Search operation type
     *     size?: int, // Number of hits to return (default: 10)
     *     sort?: string|array<string>, // A comma-separated list of <field>:<direction> pairs
     *     _source?: string|array<string>, // True or false to return the _source field or not, or a list of fields to return
     *     _source_excludes?: string|array<string>, // A list of fields to exclude from the returned _source field
     *     _source_includes?: string|array<string>, // A list of fields to extract and return from the _source field
     *     terminate_after?: int, // The maximum number of documents to collect for each shard, upon reaching which the query execution will terminate early.
     *     stats?: string|array<string>, // Specific 'tag' of the request for logging and statistical purposes
     *     suggest_field?: string, // Specify which field to use for suggestions
     *     suggest_mode?: string, // Specify suggest mode
     *     suggest_size?: int, // How many suggestions to return in response
     *     suggest_text?: string, // The source text for which the suggestions should be returned
     *     timeout?: int|string, // Explicit operation timeout
     *     track_scores?: bool, // Whether to calculate and return scores even if they are not used for sorting
     *     track_total_hits?: bool|int, // Indicate if the number of documents that match the query should be tracked. A number can also be specified, to accurately track the total hit count up to the number.
     *     allow_partial_search_results?: bool, // Indicate if an error should be returned if there is a partial search failure or timeout
     *     typed_keys?: bool, // Specify whether aggregation and suggester names should be prefixed by their respective types in the response
     *     version?: bool, // Specify whether to return document version as part of a hit
     *     seq_no_primary_term?: bool, // Specify whether to return sequence number and primary term of the last modification of each hit
     *     request_cache?: bool, // Specify if request cache should be used for this request or not, defaults to index level setting
     *     batched_reduce_size?: int, // The number of shard results that should be reduced at once on the coordinating node. This value should be used as a protection mechanism to reduce the memory overhead per search request if the potential number of shards in the request can be large.
     *     max_concurrent_shard_requests?: int, // The number of concurrent shard requests per node this search executes concurrently. This value should be used to limit the impact of the search on the cluster in order to limit the number of concurrent shard requests
     *     pre_filter_shard_size?: int, // A threshold that enforces a pre-filter roundtrip to prefilter search shards based on query rewriting if the number of shards the search request expands to exceeds the threshold. This filter roundtrip can limit the number of shards significantly if for instance a shard can not match any documents based on its rewrite method ie. if date filters are mandatory to match but the shard bounds and the query are disjoint.
     *     rest_total_hits_as_int?: bool, // Indicates whether hits.total should be rendered as an integer or an object in the rest search response
     *     min_compatible_shard_node?: string, // The minimum compatible version that all shards involved in search should have for this request to be successful
     *     include_named_queries_score?: bool, // Indicates whether hit.matched_queries should be rendered as a map that includes the name of the matched query associated with its score (true) or as an array containing the name of the matched queries (false)
     *     pretty?: bool, // Pretty format the returned JSON response. (DEFAULT: false)
     *     human?: bool, // Return human readable values for statistics. (DEFAULT: true)
     *     error_trace?: bool, // Include the stack trace of returned errors. (DEFAULT: false)
     *     source?: string, // The URL-encoded request definition. Useful for libraries that do not accept a request body for non-POST requests.
     *     filter_path?: string|array<string>, // A comma-separated list of filters used to reduce the response.
     *     body?: string|array<mixed>, // The search definition using the Query DSL. If body is a string must be a valid JSON.
     * } $params
     *
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function search(array $params): Elasticsearch
    {
        $result = $this->client->search($params);

        if (!$result instanceof Elasticsearch) {
            throw new AuditDriverException('Async handler is not implemented!');
        }

        return $result;
    }

    /**
     * @param array{
     *     index?: string|array<string>, // A comma-separated list of indices to restrict the results
     *     ignore_unavailable?: bool, // Whether specified concrete indices should be ignored when unavailable (missing or closed)
     *     ignore_throttled?: bool, // Whether specified concrete, expanded or aliased indices should be ignored when throttled
     *     allow_no_indices?: bool, // Whether to ignore if a wildcard indices expression resolves into no concrete indices. (This includes `_all` string or when no indices have been specified)
     *     expand_wildcards?: string, // Whether to expand wildcard expression to concrete indices that are open, closed or both.
     *     min_score?: int, // Include only documents with a specific `_score` value in the result
     *     preference?: string, // Specify the node or shard the operation should be performed on (default: random)
     *     routing?: string|array<string>, // A comma-separated list of specific routing values
     *     q?: string, // Query in the Lucene query string syntax
     *     analyzer?: string, // The analyzer to use for the query string
     *     analyze_wildcard?: bool, // Specify whether wildcard and prefix queries should be analyzed (default: false)
     *     default_operator?: string, // The default operator for query string query (AND or OR)
     *     df?: string, // The field to use as default where no field prefix is given in the query string
     *     lenient?: bool, // Specify whether format-based query failures (such as providing text to a numeric field) should be ignored
     *     terminate_after?: int, // The maximum count for each shard, upon reaching which the query execution will terminate early
     *     pretty?: bool, // Pretty format the returned JSON response. (DEFAULT: false)
     *     human?: bool, // Return human readable values for statistics. (DEFAULT: true)
     *     error_trace?: bool, // Include the stack trace of returned errors. (DEFAULT: false)
     *     source?: string, // The URL-encoded request definition. Useful for libraries that do not accept a request body for non-POST requests.
     *     filter_path?: string|array<string>, // A comma-separated list of filters used to reduce the response.
     *     body?: string|array<mixed>, // A query to restrict the results specified with the Query DSL (optional). If body is a string must be a valid JSON.
     * } $params
     *
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function count(array $params): Elasticsearch
    {
        $result = $this->client->count($params);

        if (!$result instanceof Elasticsearch) {
            throw new AuditDriverException('Async handler is not implemented!');
        }

        return $result;
    }

    /**
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function deleteDocument(string $index, int|string $id, bool $shouldReturnResult = false): bool
    {
        $result = $this->client->delete([
            'index' => $index,
            'id'    => (string) $id,
        ]);

        return $this
            ->getResult($result, $shouldReturnResult);
    }

    /**
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function deleteIndex(string $index): bool
    {
        $result = $this->client->indices()->delete([
            'index' => $index,
        ]);

        return $this
            ->getResult($result, true);
    }

    public function isAsync(): bool
    {
        return $this->client->getAsync();
    }

    /**
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function isIndexExists(string $index): bool
    {
        $result = $this->client->indices()->exists([
            'index' => $index,
        ]);

        return $this
            ->getResult($result, true);
    }

    /**
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function index(DocumentModel $model, bool $shouldReturnResult): bool
    {
        $result = $this->client->index($model->toArray());

        return $this
            ->getResult($result, $shouldReturnResult);
    }

    /**
     * @throws AuditDriverException
     */
    private function getResult(Elasticsearch|Promise $rawResult, bool $shouldReturnResult = false): bool
    {
        if (!$shouldReturnResult) {
            return false;
        }

        if (!$rawResult instanceof Elasticsearch) {
            throw new AuditDriverException('Async handler is not implemented!');
        }

        return $rawResult
            ->asBool();
    }

    public function getCaBundlePath(): bool|string
    {
        return $this->caBundlePath;
    }
}
