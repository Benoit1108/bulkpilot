<?php

declare(strict_types=1);

namespace App\Coach\Infrastructure\Ai;

use App\Coach\Application\Port\AiProvider;
use App\Coach\Application\Port\CoachAnalysis;
use App\Coach\Domain\Recommendation\CoachMessage;
use App\Coach\Domain\Rule\Finding;
use App\Coach\Domain\Rule\Kind;
use App\Coach\Domain\Settings\Personality;

/**
 * Phrases recommendations from templates (spec §73: develop without an API key). Deterministic, so
 * it is also what the Coach falls back on without an AI (spec §122). The "why" only restates the
 * figures it is given; the personality only changes the tone of the body (spec §131), and every
 * body says what to do (spec §130: actionable).
 */
final readonly class FakeAiProvider implements AiProvider
{
    public const string NAME = 'fake';

    public function generateRecommendation(CoachAnalysis $analysis): CoachMessage
    {
        [$title, $why, $bodies, $action] = $this->templates($analysis->finding);

        return new CoachMessage(
            French::typeset($title),
            French::typeset($bodies[$analysis->personality->value]),
            array_map(French::typeset(...), $why),
            French::typeset($action),
            self::NAME,
        );
    }

    /**
     * @return array{string, list<string>, array<value-of<Personality>, string>, string}
     */
    private function templates(Finding $finding): array
    {
        $f = new Figures($finding->figures);
        $target = French::kcal($f->int('targetKcal'));

        return match ($finding->kind) {
            Kind::NoTarget => [
                'Fixe un objectif calorique',
                ['Sans objectif, le Coach ne peut pas comparer ce que tu manges à ce qu’il te faut.'],
                self::tones(
                    'Fixe ton objectif dans Nutrition.',
                    'Première étape : fixe ton objectif dans Nutrition, le Coach s’occupe du reste.',
                    'Aucun objectif calorique n’est défini : fixe-le dans Nutrition.',
                ),
                'Fixe ton objectif calorique dans Nutrition.',
            ],
            Kind::NoPace => [
                'Indique le rythme visé',
                [\sprintf('Ton objectif de %s ne dit pas quelle évolution du poids tu vises.', $target)],
                self::tones(
                    'Choisis ton rythme dans les réglages du Coach.',
                    \sprintf('Dis au Coach quel rythme tu vises (par exemple %s par semaine) et il suivra ta tendance.', French::pace(0.5)),
                    'Le rythme visé n’est pas connu : règle-le dans les réglages du Coach.',
                ),
                'Choisis ton rythme visé dans les réglages du Coach.',
            ],
            Kind::IncreaseCalories, Kind::DecreaseCalories => $this->adjustment($finding, $f),
            Kind::ReachTargetFirst => [
                \sprintf('Atteins d’abord %s', $target),
                $this->calorieWhy($f),
                self::tones(
                    \sprintf('Atteins régulièrement %s avant d’augmenter la cible.', $target),
                    \sprintf('La cible actuelle suffit pour avancer : vise %s chaque jour, on verra ensuite.', $target),
                    \sprintf('La cible reste à %s tant qu’elle n’est pas atteinte : vise-la chaque jour.', $target),
                ),
                \sprintf('Atteins %s chaque jour avant d’augmenter la cible.', $target),
            ],
            Kind::BackToTarget => [
                \sprintf('Reviens à %s', $target),
                $this->calorieWhy($f),
                self::tones(
                    'Tu manges au-dessus de ta cible : reviens-y avant de la baisser.',
                    'Ta cible est bonne : en t’y tenant, le rythme devrait revenir de lui-même.',
                    \sprintf('La cible reste la même tant qu’elle est dépassée : reviens à %s par jour.', $target),
                ),
                \sprintf('Reviens à %s par jour.', $target),
            ],
            Kind::LowProtein => [
                'Protéines sous la cible',
                [
                    \sprintf('Moyenne : %s par jour sur %s.', French::grams($f->int('averageProtein')), French::count($f->int('loggedDays'), 'jour saisi', 'jours saisis')),
                    \sprintf('Objectif : %s.', French::grams($f->int('targetProtein'))),
                    \sprintf('%s sur %d à 90 %% de la cible ou plus.', French::count($f->int('daysOnProtein'), 'jour', 'jours'), $f->int('loggedDays')),
                ],
                self::tones(
                    'Ajoute une source de protéines à un ou deux repas.',
                    'Un laitage ou une portion de viande en plus par jour, et c’est bon !',
                    'Les protéines sont sous l’objectif cette semaine : ajoute-en à un ou deux repas.',
                ),
                'Ajoute une source de protéines à un ou deux repas.',
            ],
            Kind::IncompleteJournal => [
                'Journal incomplet',
                [
                    \sprintf('%s sur les %d derniers.', French::count($f->int('loggedDays'), 'jour saisi', 'jours saisis'), $f->int('days')),
                    \sprintf('Le Coach en attend au moins %d pour juger tes calories.', $f->int('neededDays')),
                ],
                self::tones(
                    'Note tes repas quelques jours de plus.',
                    'Encore quelques jours de saisie et le Coach pourra t’aider sur les calories.',
                    'Trop peu de jours saisis pour juger les calories : note tes repas quelques jours de plus.',
                ),
                'Note tes repas quelques jours de plus.',
            ],
            Kind::FewWeighIns => [
                'Pèse-toi plus souvent',
                [
                    \sprintf('%s ces 7 derniers jours.', French::count($f->int('weighIns7'), 'pesée', 'pesées')),
                    \sprintf('Il en faut au moins %d par semaine pour une tendance fiable.', $f->int('neededPerWeek')),
                ],
                self::tones(
                    'Pèse-toi le matin, au moins 3 fois par semaine.',
                    'Quelques pesées de plus et ta tendance sera bien plus parlante.',
                    'Pas assez de pesées pour une tendance fiable : pèse-toi au moins 3 fois par semaine.',
                ),
                'Pèse-toi le matin, au moins 3 fois par semaine.',
            ],
            Kind::MissedSessions => [
                'Séances manquées',
                [\sprintf('%s sur %s ces 7 derniers jours.', French::count($f->int('sessions'), 'séance', 'séances'), French::count($f->int('planned'), 'prévue', 'prévues'))],
                self::tones(
                    'Reprends une séance cette semaine.',
                    'Reprends une séance cette semaine, à ton rythme : chaque retour compte.',
                    'Moins de séances que prévu par le programme : reprends une séance cette semaine.',
                ),
                'Reprends une séance cette semaine.',
            ],
            Kind::Stall => $this->stall($f),
            Kind::Records => [
                \sprintf('%s cette semaine', French::count($f->int('records'), 'record', 'records')),
                [\sprintf('%s en %s ces 7 derniers jours.', French::count($f->int('records'), 'record battu', 'records battus'), French::count($f->int('sessions'), 'séance', 'séances'))],
                self::tones('Continue ton programme.', 'Bravo, continue comme ça !', 'Des records ont été battus cette semaine : continue ton programme.'),
                'Continue ton programme.',
            ],
        };
    }

    /**
     * The tone follows the situation: gaining, cutting or maintaining, too slow or too fast.
     *
     * @return array{string, list<string>, array<value-of<Personality>, string>, string}
     */
    private function adjustment(Finding $finding, Figures $f): array
    {
        $by = $finding->proposal->byKcal ?? 0;
        $to = French::kcal($finding->proposal?->toKcal() ?? 0);
        $pace = $f->float('paceKgPerWeek');
        $encouraging = match (true) {
            $by > 0 && $pace > 0 => \sprintf('Tu tiens bien ta cible : on peut passer à %s par jour dès demain.', $to),
            $by > 0 && $pace < 0 => \sprintf('Tu perds plus vite que prévu : remontons à %s par jour dès demain, pour garder tes muscles.', $to),
            $by > 0 => \sprintf('Ton poids baisse un peu : remontons à %s par jour dès demain.', $to),
            $pace > 0 => \sprintf('Ta régularité paie, un peu trop vite même : passons à %s par jour dès demain.', $to),
            $pace < 0 => \sprintf('Tu tiens bien ta cible : on peut descendre à %s par jour dès demain pour relancer la perte.', $to),
            default => \sprintf('Ton poids monte un peu : redescendons à %s par jour dès demain.', $to),
        };

        return [
            \sprintf('Proposition : %s', French::signedKcal($by)),
            $this->calorieWhy($f),
            self::tones(
                \sprintf('Passe à %s par jour à partir de demain.', $to),
                $encouraging,
                \sprintf('Nouvel objectif proposé : %s par jour, à partir de demain. Accepte-le ou refuse-le.', $to),
            ),
            \sprintf('Passe à %s par jour (proposition à valider).', $to),
        ];
    }

    /**
     * The spec §69 explanation: the trend, what was eaten, the target, the share of it reached.
     *
     * @return list<string>
     */
    private function calorieWhy(Figures $f): array
    {
        return [
            \sprintf('Ton poids évolue de %s par semaine sur 4 semaines, pour %s visés.', French::signedKg($f->float('kgPerWeek')), French::pace($f->float('paceKgPerWeek'))),
            \sprintf('Calories moyennes : %s (%d jours saisis sur %d).', French::kcal($f->float('averageKcal')), $f->int('loggedDays'), $f->int('days')),
            \sprintf('Objectif : %s.', French::kcal($f->int('targetKcal'))),
            \sprintf('Tu atteins donc environ %s de ta cible.', French::percent($f->int('percentOfTarget'))),
        ];
    }

    /**
     * Spec §67: nutrition first, then recovery; never more volume.
     *
     * @return array{string, list<string>, array<value-of<Personality>, string>, string}
     */
    private function stall(Figures $f): array
    {
        $exercises = $f->stalls();
        $why = array_map(
            static fn (array $stall): string => \sprintf('%s : pas de record depuis %s (%s).', $stall['exercise'], French::count($stall['sessions'], 'séance', 'séances'), French::day($stall['since'])),
            $exercises,
        );
        $percent = $f->nullableInt('percentOfTarget');
        if (null !== $percent) {
            $why[] = $percent < 90
                ? \sprintf('Tes calories sont à %s de ta cible : c’est la première piste.', French::percent($percent))
                : \sprintf('Tes calories sont à %s de ta cible : l’alimentation n’explique pas la stagnation.', French::percent($percent));
        }
        $title = 1 === \count($exercises) ? \sprintf('Stagnation : %s', $exercises[0]['exercise']) : \sprintf('Stagnation sur %d exercices', \count($exercises));
        $action = null !== $percent && $percent < 90
            ? 'Atteins ta cible calorique avant de toucher au programme.'
            : 'Soigne ton sommeil et ta récupération avant de toucher au programme.';

        return [
            $title,
            $why,
            self::tones(
                'Vérifie ton sommeil et ta récupération avant de toucher au programme.',
                'Ça arrive à tout le monde : soigne ton sommeil et ta récupération, les progrès reviendront.',
                'Pistes : alimentation, sommeil, récupération. Le Coach ne change jamais le volume de lui-même : vérifie-les d’abord.',
            ),
            $action,
        ];
    }

    /**
     * @return array<value-of<Personality>, string>
     */
    private static function tones(string $direct, string $encouraging, string $neutral): array
    {
        return [Personality::Direct->value => $direct, Personality::Encouraging->value => $encouraging, Personality::Neutral->value => $neutral];
    }
}
