<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Lists the accounts that can sign in, and where each one signs in.
 *
 * This reads the database rather than the seeders, so it stays true after
 * accounts are added or moved by hand.
 */
class ListAccountsCommand extends Command
{
    protected $signature = 'accounts:list';

    protected $description = 'List the accounts that can sign in to each panel';

    public function handle(): int
    {
        $rows = [
            ...$this->productTeamRows(),
            ...$this->restaurantRows(),
        ];

        if ($rows === []) {
            $this->components->warn('No accounts yet. Run `php artisan db:seed` to create them.');

            return self::SUCCESS;
        }

        $this->table(['Account', 'Email', 'Signs in at'], $rows);

        $this->components->info('Sign-in is by emailed code; no passwords are stored.');

        return self::SUCCESS;
    }

    /**
     * The the product team, who sign in on the root domain.
     *
     * @return list<array{string, string, string}>
     */
    private function productTeamRows(): array
    {
        $rows = [];

        foreach (User::query()->superAdmins()->orderBy('email')->get() as $user) {
            $rows[] = [
                'Super admin',
                $user->email,
                route('platform.login'),
            ];
        }

        return $rows;
    }

    /**
     * Everyone who runs a restaurant, and where they sign in.
     *
     * An admin runs the restaurant from its panel on a laptop. Staff have no
     * surface of their own, so only admins are listed.
     *
     * @return list<array{string, string, string}>
     */
    private function restaurantRows(): array
    {
        $restaurants = Restaurant::query()
            ->with(['users' => fn ($query) => $query->with('roles')])
            ->orderBy('name')
            ->get();

        $rows = [];

        foreach ($restaurants as $restaurant) {
            foreach ($restaurant->users as $user) {
                if ($user->hasRole(Role::Admin->value)) {
                    $rows[] = [
                        $restaurant->name.' admin',
                        $user->email,
                        $restaurant->signInUrl(),
                    ];
                }
            }
        }

        return $rows;
    }
}
