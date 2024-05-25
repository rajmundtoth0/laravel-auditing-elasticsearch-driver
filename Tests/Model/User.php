<?php

namespace rajmundtoth0\AuditDriver\Tests\Model;

use rajmundtoth0\AuditDriver\Traits\ElasticSearchAuditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property string $name
 * @property string $email
 * @property Carbon $email_verified_at
 * @property string $password
 * @property Collection<int, mixed> $auditLog
 */
class User extends Model implements AuditableContract
{
    use \Illuminate\Auth\Authenticatable;
    use ElasticSearchAuditable, Auditable;
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
