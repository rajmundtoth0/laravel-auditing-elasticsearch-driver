<?php

namespace rajmundtoth0\AuditDriver\Services;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Elastic\Transport\Exception\NoNodeAvailableException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use OwenIt\Auditing\Contracts\Audit;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Contracts\AuditDriver;
use OwenIt\Auditing\Exceptions\AuditingException;
use rajmundtoth0\AuditDriver\Client\ElasticsearchClient;
use rajmundtoth0\AuditDriver\Exceptions\AuditDriverException;
use rajmundtoth0\AuditDriver\Exceptions\AuditDriverMissingCaCertException;
use rajmundtoth0\AuditDriver\Jobs\IndexAuditDocumentJob;
use rajmundtoth0\AuditDriver\Models\DocumentModel;
use Ramsey\Uuid\Uuid;
use RuntimeException;

class ElasticsearchAuditService implements AuditDriver
{
    public string $index;

    /** @var class-string<Audit> */
    private string $implementation;

    private string $auditType;

    private bool $useQueue;

    private string $queueName;

    private string $queueConnection;

    /**
     * @throws AuditDriverMissingCaCertException
     */
    public function __construct(
        private readonly ElasticsearchClient $client,
    ) {
        $this->loadConfigs();
        $this->client->setClient();
    }

    private function loadConfigs(): void
    {
        $index           = Config::string('audit.drivers.elastic.index', 'laravel_auditing');
        $auditType       = Config::string('audit.drivers.elastic.type', '');
        $implementation  = Config::string('audit.implementation', Audit::class);
        $useQueue        = Config::boolean('audit.drivers.queue.enabled', false);
        $queueName       = Config::string('audit.drivers.queue.name', '');
        $queueConnection = Config::string('audit.drivers.queue.connection', '');

        assert(is_subclass_of($implementation, Audit::class));
        if ($useQueue) {
            assert($queueName);
            assert($queueConnection);
        }

        $this->index           = $index;
        $this->auditType       = $auditType;
        $this->implementation  = $implementation;
        $this->useQueue        = $useQueue;
        $this->queueName       = $queueName;
        $this->queueConnection = $queueConnection;
    }

    /**
     * @throws AuditDriverException
     * @throws AuditingException
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function audit(Auditable $model): Audit
    {
        $this->indexDocument($model->toAudit());
        $implementation = new $this->implementation();

        return $implementation;
    }

    /**
     * @param Auditable&Model $model
     *
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function prune(Auditable $model, bool $shouldReturnResult = false): bool
    {
        if ($model->getAuditThreshold() <= 0) {
            return false;
        }
        $key = $model->getKey();
        assert(is_string($key) || is_int($key));

        return $this->deleteAuditDocument($key, $shouldReturnResult);
    }

    /**
     * @param array<mixed> $model
     *
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function indexDocument(array $model, bool $shouldReturnResult = false): ?bool
    {
        $document = new DocumentModel(
            index: $this->index,
            id: Uuid::uuid4(),
            type: $this->auditType,
            body: $model,
        );

        if (!$this->useQueue) {
            return $this->index($document, $shouldReturnResult);
        }

        dispatch(new IndexAuditDocumentJob($document))
            ->onQueue($this->queueName)
            ->onConnection($this->queueConnection);

        return null;
    }

    /**
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function index(DocumentModel $document, bool $shouldReturnResult = false): ?bool
    {
        return $this->client->index($document, $shouldReturnResult);
    }

    /**
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function searchAuditDocument(
        Auditable&Model $model,
        int $pageSize = 10_000,
        ?int $from = null,
        string $sort = 'desc',
    ): Elasticsearch {
        $from ??= $model->getAuditThreshold() - 1;

        $key = $model->getKey();

        $params = [
            'index' => $this->index,
            'type'  => $this->auditType,
            'size'  => $pageSize,
            'from'  => $from,
            'body'  => [
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'term' => [
                                    'auditable_id' => $key,
                                ],
                            ],
                            [
                                'term' => [
                                    'auditable_type' => $model->getMorphClass(),
                                ],
                            ],
                        ],
                    ],
                ],
                'sort' => [
                    'created_at' => [
                        'order' => $sort,
                    ],
                ],
                'track_scores' => true,
            ],
        ];

        return $this->client->search($params);
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
     * } $query
     *
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function search(array $query = []): Elasticsearch
    {
        return $this->client->search($query);
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
     * } $query
     *
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function count(array $query = []): Elasticsearch
    {
        return $this->client->count($query);
    }

    /**
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function deleteAuditDocument(int|string $documentId, bool $shouldReturnResult = false): bool
    {
        return $this
            ->client
            ->deleteDocument($this->index, $documentId, $shouldReturnResult);
    }

    /**
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     */
    public function deleteIndex(): bool
    {
        return $this
            ->client
            ->deleteIndex($this->index);
    }

    /**
     * @throws AuditDriverException
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws NoNodeAvailableException
     * @throws ServerResponseException
     * @throws RuntimeException
     */
    public function createIndex(): string
    {
        if ($this->client->isIndexExists($this->index)) {
            return $this->index;
        }
        $this->client->createIndex($this->index, $this->auditType);

        $this->client->updateAliases($this->index);

        return $this->index;
    }

    /**
     * @throws AuditDriverMissingCaCertException
     */
    public function setClient(?Client $client = null): self
    {
        $this->client->setClient(
            client: $client,
        );

        return $this;
    }

    public function isAsync(): bool
    {
        return $this->client->isAsync();
    }

    public function getIndexName(): string
    {
        return $this->index;
    }
}
