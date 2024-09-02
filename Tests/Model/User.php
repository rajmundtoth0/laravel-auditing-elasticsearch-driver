<?php

namespace rajmundtoth0\AuditDriver\Tests\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use rajmundtoth0\AuditDriver\Traits\ElasticSearchAuditable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon $email_verified_at
 * @property string $password
 * @property Collection<int, mixed> $auditLog
 */
class User extends Model implements AuditableContract
{
    use \Illuminate\Auth\Authenticatable;
    use ElasticSearchAuditable;
    use \OwenIt\Auditing\Auditable;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
    ];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
