<?php

namespace App\Services\System\Permission;

/**
 * The actions that ask for a supervisor's username and password before they go
 * through.
 *
 * One catalogue, read by the endpoint that issues an override and by the role
 * screen that decides who may give one. Keeping these in step matters: an
 * action the issuing endpoint refuses but the role screen still offers is a
 * checkbox that does nothing, and the reverse is an action anybody can
 * authorise because nothing was ever configured for it.
 *
 * The 000006 migration carries its own copy of the keys on purpose — a
 * migration that read this list would change meaning whenever the list did.
 */
class SupervisorActions
{
    /** Keyed by the action string; described in the words the people configuring them use. */
    private const ACTIONS = [
        'sales.credit_sale' => [
            'label' => 'Place a credit sale',
            'description' => 'Commits the business to collecting the money later.',
        ],
        'sales.approve_return' => [
            'label' => 'Approve a sales return',
            'description' => 'Puts stock back and moves money out.',
        ],
        'checks.bounce' => [
            'label' => 'Record a bounced check',
            'description' => 'Decides money a customer appeared to pay never arrived, and puts the rep back on the hook.',
        ],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::ACTIONS);
    }

    public static function exists(string $action): bool
    {
        return array_key_exists($action, self::ACTIONS);
    }

    /**
     * Every action with its wording, for display.
     *
     * @return list<array{key: string, label: string, description: string}>
     */
    public static function all(): array
    {
        $actions = [];

        foreach (self::ACTIONS as $key => $meta) {
            $actions[] = ['key' => $key] + $meta;
        }

        return $actions;
    }
}
