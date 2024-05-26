<?php

namespace rajmundtoth0\AuditDriver\Tests\Model;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'id'   => 1,
            'name' => 'John Doe',
        ];
    }
}
