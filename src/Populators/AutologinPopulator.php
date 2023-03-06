<?php

namespace Crm\UsersModule\Populator;

use Crm\ApplicationModule\Populator\AbstractPopulator;

class AutologinPopulator extends AbstractPopulator
{
    /**
     * @param \Symfony\Component\Console\Helper\ProgressBar $progressBar
     */
    public function seed($progressBar)
    {
        $autologin = $this->database->table('autologin_tokens');
        for ($i = 0; $i < $this->count; $i++) {
            $maxUsed = $this->faker->randomDigitNotNull;
            $autologin->insert([
                'token' => $this->faker->md5,
                'user_id' => $this->getRecord('users'),
                'created_at' => $this->faker->dateTimeBetween('-2 years'),
                'valid_from' => $this->faker->dateTimeBetween('-2 years'),
                'valid_to' => $this->faker->dateTimeBetween('-2 years'),
                'used_count' => random_int(1, $maxUsed),
                'max_count' => $maxUsed,
            ]);
            $progressBar->advance();
        }
    }
}
