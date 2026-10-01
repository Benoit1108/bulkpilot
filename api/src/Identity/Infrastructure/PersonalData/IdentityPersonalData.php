<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\PersonalData;

use App\Shared\Application\PersonalData\Dataset;
use App\Shared\Application\PersonalData\ExportScope;
use App\Shared\Application\PersonalData\PersonalData;
use App\Shared\Domain\Identifier\UserId;
use App\Shared\Infrastructure\Persistence\Doctrine\PersonalRows as R;
use Doctrine\DBAL\Connection;

/**
 * The account (ADR-0030): profile, preferences, devices, linked providers. Never a secret: no
 * password hash, two-factor secret, recovery code, token or session identifier. Erased last,
 * after every other context.
 */
final readonly class IdentityPersonalData implements PersonalData
{
    public const string DOMAIN = 'identity';
    private const array SEXES = ['female' => 'Femme', 'male' => 'Homme'];
    private const array PROVIDERS = ['google' => 'Google', 'apple' => 'Apple'];

    public function __construct(private Connection $connection)
    {
    }

    public function domain(): string
    {
        return self::DOMAIN;
    }

    public function title(): string
    {
        return 'Compte';
    }

    public function periodFiltered(): bool
    {
        return false;
    }

    public function erasedLast(): bool
    {
        return true;
    }

    public function export(UserId $userId, ExportScope $scope): array
    {
        $c = $this->connection;
        $account = R::rows($c, 'SELECT email, profile_first_name, profile_birth_date, profile_sex, profile_height_cm, email_verified_at, two_factor_enabled_at, registered_at FROM users WHERE id = :user', $userId);
        $preferences = R::rows($c, 'SELECT time_zone, dashboard_layout, updated_at FROM user_preferences WHERE owner_id = :user', $userId);
        $sessions = R::rows($c, 'SELECT device_label, opened_at, last_seen_at, revoked_at FROM user_sessions WHERE user_id = :user', $userId, order: 'opened_at');
        $providers = R::rows($c, 'SELECT provider, linked_at FROM user_external_identities WHERE user_id = :user', $userId, order: 'linked_at');

        return [
            new Dataset('compte', 'Compte', ['Adresse email', 'Prénom', 'Date de naissance', 'Sexe', 'Taille (cm)', 'Email confirmé le', 'Double authentification depuis', 'Inscrit le'], array_map(static fn (array $row): array => [
                R::string($row['email']), R::string($row['profile_first_name']), R::string($row['profile_birth_date']), R::label($row['profile_sex'], self::SEXES),
                R::int($row['profile_height_cm']), R::time($row['email_verified_at']), R::time($row['two_factor_enabled_at']), R::time($row['registered_at']),
            ], $account)),
            new Dataset('preferences', 'Préférences', ['Fuseau horaire', 'Tableau de bord', 'Modifiées le'], array_map(static fn (array $row): array => [
                R::string($row['time_zone']), self::layout(R::json($row['dashboard_layout'])), R::time($row['updated_at']),
            ], $preferences)),
            new Dataset('appareils', 'Appareils connectés', ['Appareil', 'Connecté le', 'Dernière activité', 'Déconnecté le'], array_map(static fn (array $row): array => [
                R::string($row['device_label']), R::time($row['opened_at']), R::time($row['last_seen_at']), R::time($row['revoked_at']),
            ], $sessions)),
            new Dataset('connexions_externes', 'Connexions externes', ['Fournisseur', 'Liée le'], array_map(static fn (array $row): array => [
                R::label($row['provider'], self::PROVIDERS), R::time($row['linked_at']),
            ], $providers)),
        ];
    }

    public function files(UserId $userId, ExportScope $scope): array
    {
        return [];
    }

    /**
     * The erasure locked the account before calling it (EraseAccountHandler): no sign-in can
     * cancel the deletion in between.
     */
    public function erase(UserId $userId): void
    {
        $id = ['user' => $userId->value];
        if (false === $this->connection->fetchOne('SELECT 1 FROM users WHERE id = :user', $id)) {
            return;
        }
        // The devices' HTTP sessions (PdoSessionHandler) hold the account's id in their data.
        $this->connection->executeStatement('DELETE FROM sessions WHERE position(CAST(:needle AS BYTEA) IN sess_data) > 0', ['needle' => $userId->value]);
        R::deleteOwned($this->connection, $userId, 'user_id', 'user_sessions', 'one_time_tokens', 'user_external_identities');
        R::deleteOwned($this->connection, $userId, 'owner_id', 'user_preferences');
        $this->connection->executeStatement('DELETE FROM users WHERE id = :user', $id);
    }

    /**
     * @param array<mixed> $layout
     */
    private static function layout(array $layout): ?string
    {
        if ([] === $layout) {
            return null;
        }
        $shown = [];
        foreach ($layout as $widget) {
            if (\is_array($widget) && \is_string($widget['key'] ?? null)) {
                $shown[] = $widget['key'].(false === ($widget['visible'] ?? true) ? ' (masqué)' : '');
            }
        }

        return implode(', ', $shown);
    }
}
