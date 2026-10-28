<?php

namespace App\Core\Execution;

/**
 * Porte d'entrée unique pour confier une tâche au Core. La tâche est écrite dans la file persistante (table `jobs`) après la
 * validation de la transaction en cours ; la requête qui l'a demandée la fait avancer après l'envoi de sa réponse (voir
 * RunBackgroundWork), sans jamais promettre l'instant où le résultat arrive.
 */
final class Execution
{
    public const REQUESTED = 'gamad.execution.requested';

    public static function dispatch(object $job): void
    {
        $job->onConnection(BackgroundRunner::CONNECTION)->onQueue(BackgroundRunner::QUEUE)->afterCommit();
        dispatch($job);

        request()->attributes->set(self::REQUESTED, true);
    }
}
