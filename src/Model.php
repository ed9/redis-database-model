<?php

namespace Ed9\RedisDocument;

use Ed9\RedisDocument\Casts\UuidCast;
use Averias\RedisJson\Factory\RedisJsonClientFactory;
use Averias\RedisJson\Client\RedisJsonClientInterface;
use Illuminate\Database\Eloquent\Concerns\HasAttributes;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Concerns\GuardsAttributes;

class Model
{
    use GuardsAttributes;
    use HasAttributes;
    use HasTimestamps;

    /**
     * The name of the "created at" column.
     *
     * @var string|null
     */
    const CREATED_AT = 'created_at';

    /**
     * The name of the "updated at" column.
     *
     * @var string|null
     */
    const UPDATED_AT = 'updated_at';

    /**
     * The name of the redis database connection.
     *
     * @var string
     */
    protected string $connection = 'default';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected string $primaryKey = 'id';

    /**
     * The cast "type" of the primary key ID.
     *
     * @var string
     */
    protected string $keyType = UuidCast::class;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public bool $incrementing = true;

    /**
     * Indicates if the model exists.
     *
     * @var bool
     */
    public bool $exists = false;

    protected string $table;


    /**
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param array $attributes
     * @return $this
     *
     * @throws \Illuminate\Database\Eloquent\MassAssignmentException
     */
    public function fill(array $attributes): static
    {
        $totallyGuarded = $this->totallyGuarded();

        $fillable = $this->fillableFromArray($attributes);

        foreach ($fillable as $key => $value) {
            // The developers may choose to place some attributes in the "fillable" array
            // which means only those attributes may be set through mass assignment to
            // the model, and all others will just get ignored for security reasons.
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            } elseif ($totallyGuarded || static::preventsSilentlyDiscardingAttributes()) {
                if (isset(static::$discardedAttributeViolationCallback)) {
                    call_user_func(static::$discardedAttributeViolationCallback, $this, [$key]);
                } else {
                    throw new MassAssignmentException(
                        sprintf(
                            'Add [%s] to fillable property to allow mass assignment on [%s].',
                            $key,
                            get_class($this)
                        )
                    );
                }
            }
        }

        if (count($attributes) !== count($fillable) &&
            static::preventsSilentlyDiscardingAttributes()) {
            $keys = array_diff(array_keys($attributes), array_keys($fillable));

            if (isset(static::$discardedAttributeViolationCallback)) {
                call_user_func(static::$discardedAttributeViolationCallback, $this, $keys);
            } else {
                throw new MassAssignmentException(
                    sprintf(
                        'Add fillable property [%s] to allow mass assignment on [%s].',
                        implode(', ', $keys),
                        get_class($this)
                    )
                );
            }
        }

        return $this;
    }

    /**
     * @return bool
     */
    public static function preventsSilentlyDiscardingAttributes(): bool
    {
        return static::$modelsShouldPreventSilentlyDiscardingAttributes;
    }

    /**
     * @var bool
     */
    protected static bool $modelsShouldPreventSilentlyDiscardingAttributes = false;

    /**
     * @var callable
     */
    protected static $discardedAttributeViolationCallback;

    /**
     * Save the model to the database.
     *
     * @return $this
     */
    public function save(): static
    {
        $this->setAttribute(
            $this->getKeyName(),
            $this->castAttribute($this->getKeyName(), null)
        );

        $redis = $this->getRedisConnection();

        dump(
            $redis->jsonset(
                $this->getRedisKey(),
                '$',
                json_encode($this->getAttributes()),
            )
        );
    }

    public function getIncrementing(): bool
    {
        return $this->incrementing;
    }

    /**
     * Get the primary key for the model.
     *
     * @return string
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Get the auto-incrementing key type.
     *
     * @return string
     */
    public function getKeyType(): string
    {
        return $this->keyType;
    }

    public function getRedisKey(): string
    {
        return sprintf('%s:%s', $this->table, $this->getKey());
    }

    public function getKey(): string
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Dynamically retrieve attributes on the model.
     *
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attributes on the model.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Contains redis connections.
     *
     * @var RedisJsonClientInterface[]
     */
    private static array $redisModelConnections = [];

    /**
     * @return RedisJsonClientInterface
     * @throws \Averias\RedisJson\Exception\RedisClientException
     */
    private function getRedisConnection(): RedisJsonClientInterface
    {
        if (!isset(static::$redisModelConnections[$this->connection])) {
            $redisJsonClientFactory = new RedisJsonClientFactory();
            static::$redisModelConnections[$this->connection] = $redisJsonClientFactory->createClient([
                'host' => config(sprintf('database.redis.%s.host', $this->connection)),
                'port' => config(sprintf('database.redis.%s.port', $this->connection)),
                'database' => config(sprintf('database.redis.%s.database', $this->connection))
            ]);
        }

        return static::$redisModelConnections[$this->connection];
    }
}
