<?php

/**
 * FleetAppId — resolve a Conduction fleet app's installed id across the rename.
 *
 * The fleet renamed: `openconnector` became `integriq`, `docudesk` became
 * `filinq`, and so on. As of September 2026 the new names have reached
 * `main`, `beta` and `development` alike, so a current instance answers to the
 * new id — but an instance pinned to an older release still answers only to
 * the old one, and both are in the field at once.
 *
 * That matters because every cross-app reference is a DUCK-TYPED RUNTIME
 * LOOKUP. `IAppManager::isInstalled('openconnector')` against an instance
 * running `integriq` does not error — it returns false, and the integration
 * silently does nothing. A hard swap to the new id has the same failure in
 * the other direction against an older deployment.
 *
 * So neither name alone is correct. This resolver takes a LIST, newest first,
 * and returns whichever the instance actually has. Callers ask for the
 * canonical (new) name and get back the identity that really exists.
 *
 * The rename moved TWO things — the app id and the PHP namespace — and each
 * breaks its own set of call sites. `resolve()`, `isInstalled()` and
 * `appPath()` cover the id. `classCandidates()`, `resolveClass()`,
 * `getService()` and `isInstanceOf()` cover the namespace. Fixing one half
 * and not the other leaves the integration just as dark, which is how thirteen
 * filinq bindings across five apps stayed broken for a fortnight.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category Support
 * @package  OCA\Larpinq\Support
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Larpinq\Support;

use OCP\App\IAppManager;
use Psr\Container\ContainerInterface;
use Throwable;

/**
 * Resolves fleet app ids across the in-flight rename.
 *
 * @spec exclude infrastructure utility with no feature requirement of its own; it is
 *   exercised through the features that call it
 */
final class FleetAppId
{

    /**
     * Candidate ids per canonical app, NEWEST FIRST.
     *
     * Order is the contract: the first entry that is installed wins, so a
     * fully migrated instance resolves to the new id and a beta/main instance
     * falls back to the old one. Adding a new rename means prepending, never
     * replacing — dropping the old id here is what silently breaks the
     * integrations this class exists to protect.
     *
     * @var array<string, list<string>>
     */
    private const CANDIDATES = [
        'integriq' => ['integriq', 'openconnector'],
        'filinq'   => ['filinq', 'docudesk'],
        'thematiq' => ['thematiq', 'nldesign'],
        'stackiq'  => ['stackiq', 'softwarecatalog'],
        'larpinq'  => ['larpinq', 'larpingapp'],
        'dossiq'   => ['dossiq', 'procest'],
        'learniq'  => ['learniq', 'scholiq'],
        'decidiq'  => ['decidiq', 'decidesk'],
        'buildiq'  => ['buildiq', 'openbuild'],
        'keepiq'   => ['keepiq', 'doriath'],
    ];


    /**
     * The id this instance actually has installed, or null if none is.
     *
     * @param IAppManager $appManager The Nextcloud app manager.
     * @param string      $canonical  Canonical (new) app name, e.g. 'integriq'.
     *
     * @return string|null The installed id, or null when the app is absent.
     * @spec exclude infrastructure utility with no feature requirement of its own; it is
     *   exercised through the features that call it
     */
    public static function resolve(IAppManager $appManager, string $canonical): ?string
    {
        foreach ((self::CANDIDATES[$canonical] ?? [$canonical]) as $candidate) {
            try {
                if ($appManager->isInstalled($candidate) === true) {
                    return $candidate;
                }
            } catch (Throwable $e) {
                // An app manager that cannot answer for one candidate must not
                // abort the search — the next candidate may still resolve.
                continue;
            }
        }

        return null;

    }//end resolve()


    /**
     * Whether any candidate id for this app is installed.
     *
     * @param IAppManager $appManager The Nextcloud app manager.
     * @param string      $canonical  Canonical (new) app name, e.g. 'integriq'.
     *
     * @return bool True when the app is present under some id.
     * @spec exclude infrastructure utility with no feature requirement of its own; it is
     *   exercised through the features that call it
     */
    public static function isInstalled(IAppManager $appManager, string $canonical): bool
    {
        return self::resolve(appManager: $appManager, canonical: $canonical) !== null;

    }//end isInstalled()


    /**
     * Whether the app is installed AND enabled for the current user.
     *
     * Resolves the id first so the enabled check runs against the same id the
     * instance actually has, rather than against a name it never registered.
     *
     * @param IAppManager $appManager The Nextcloud app manager.
     * @param string      $canonical  Canonical (new) app name, e.g. 'integriq'.
     *
     * @return bool True when present and enabled for the current user.
     * @spec exclude infrastructure utility with no feature requirement of its own; it is
     *   exercised through the features that call it
     */
    public static function isEnabledForUser(IAppManager $appManager, string $canonical): bool
    {
        $id = self::resolve(appManager: $appManager, canonical: $canonical);
        if ($id === null) {
            return false;
        }

        try {
            return $appManager->isEnabledForUser($id);
        } catch (Throwable $e) {
            return false;
        }

    }//end isEnabledForUser()


    /**
     * Build an app-scoped path using the id the instance actually has.
     *
     * A URL like `/apps/openconnector/api/sources` is a routing key: Nextcloud
     * mounts routes under the REGISTERED app id, so the path is only valid for
     * the id that is really installed. Hardcoding either name yields a 404 on
     * half the fleet.
     *
     * @param IAppManager $appManager The Nextcloud app manager.
     * @param string      $canonical  Canonical (new) app name, e.g. 'integriq'.
     * @param string      $suffix     Path after the app segment, no leading slash.
     *
     * @return string|null The path, or null when the app is not installed.
     * @spec exclude infrastructure utility with no feature requirement of its own; it is
     *   exercised through the features that call it
     */
    public static function appPath(IAppManager $appManager, string $canonical, string $suffix = ''): ?string
    {
        $id = self::resolve(appManager: $appManager, canonical: $canonical);
        if ($id === null) {
            return null;
        }

        $path = '/apps/'.$id;
        if ($suffix !== '') {
            $path .= '/'.ltrim($suffix, '/');
        }

        return $path;

    }//end appPath()


    /**
     * Candidate PHP namespaces per canonical app, NEWEST FIRST.
     *
     * The rename moved each app's PSR-4 root as well as its id, and the two
     * halves break differently. A stale id makes `isInstalled()` answer false;
     * a stale namespace makes `class_exists()` answer false and
     * `IContainer::get()` throw. Both fail into the same silent no-op, because
     * every cross-app binding in the fleet is guarded.
     *
     * `appPath()` and `resolve()` above cover the id half. This map covers the
     * class half, and the two are NOT interchangeable: an app can be installed
     * under its new id while a consumer still names its old namespace, which is
     * exactly the state that took thirteen filinq integrations dark in August
     * 2026 without a single error being logged.
     *
     * Every pair below was read out of that app's own composer.json history,
     * not inferred from its id — `openbuild` shipped `OCA\OpenBuilt`, which no
     * naming rule would have produced.
     *
     * Order is the contract, as it is for CANDIDATES: newest first, old names
     * retained. Dropping an old name here re-breaks every instance still
     * running a release from before that app's rename.
     *
     * @var array<string, list<string>>
     */
    private const NAMESPACES = [
        'integriq' => ['OCA\Integriq', 'OCA\OpenConnector'],
        'filinq'   => ['OCA\Filinq', 'OCA\DocuDesk'],
        'thematiq' => ['OCA\Thematiq', 'OCA\NLDesign'],
        'stackiq'  => ['OCA\Stackiq', 'OCA\SoftwareCatalog'],
        'larpinq'  => ['OCA\Larpinq', 'OCA\LarpingApp'],
        'dossiq'   => ['OCA\Dossiq', 'OCA\Procest'],
        'learniq'  => ['OCA\Learniq', 'OCA\Scholiq'],
        'decidiq'  => ['OCA\Decidiq', 'OCA\Decidesk'],
        'buildiq'  => ['OCA\Buildiq', 'OCA\OpenBuilt'],
        'keepiq'   => ['OCA\Keepiq', 'OCA\Doriath'],
    ];


    /**
     * Every fully-qualified name a class could have, newest namespace first.
     *
     * Use this rather than {@see self::resolveClass()} when the answer is
     * needed BEFORE the other app's autoloader is certain to be registered —
     * most importantly when registering an event listener during
     * `Application::register()`. Registering the listener under every candidate
     * name is safe: dispatch matches on the concrete event class, so the names
     * that never materialise simply never fire.
     *
     * @param string $canonical Canonical (new) app name, e.g. 'filinq'.
     * @param string $relative  Class name below the app root, e.g. 'Service\PdfService'.
     *
     * @return list<string> Candidate FQCNs, newest first; empty when unknown.
     * @spec exclude infrastructure utility with no feature requirement of its own; it is
     *   exercised through the features that call it
     */
    public static function classCandidates(string $canonical, string $relative): array
    {
        $relative   = ltrim($relative, '\\');
        $candidates = [];

        foreach ((self::NAMESPACES[$canonical] ?? []) as $namespace) {
            $candidates[] = $namespace.'\\'.$relative;
        }

        return $candidates;

    }//end classCandidates()


    /**
     * The fully-qualified name this instance actually has, or null.
     *
     * @param string $canonical Canonical (new) app name, e.g. 'filinq'.
     * @param string $relative  Class name below the app root, e.g. 'Service\PdfService'.
     *
     * @return string|null The FQCN that exists, or null when none does.
     * @spec exclude infrastructure utility with no feature requirement of its own; it is
     *   exercised through the features that call it
     */
    public static function resolveClass(string $canonical, string $relative): ?string
    {
        foreach (self::classCandidates(canonical: $canonical, relative: $relative) as $fqcn) {
            if (class_exists($fqcn) === true || interface_exists($fqcn) === true) {
                return $fqcn;
            }
        }

        return null;

    }//end resolveClass()


    /**
     * Fetch a service from another fleet app, whatever namespace it ships under.
     *
     * Replaces `$container->get('OCA\SomeOldName\Service\Thing')`, which throws
     * when that app has renamed and — because every call site of that shape is
     * wrapped in a try/catch that degrades gracefully — turns a rename into a
     * feature that quietly stops working rather than an error anybody sees.
     *
     * @param ContainerInterface $container The service container.
     * @param string             $canonical Canonical (new) app name, e.g. 'filinq'.
     * @param string             $relative  Class name below the app root, e.g. 'Service\PdfService'.
     *
     * @return object|null The service, or null when no candidate resolves.
     * @spec exclude infrastructure utility with no feature requirement of its own; it is
     *   exercised through the features that call it
     */
    public static function getService(ContainerInterface $container, string $canonical, string $relative): ?object
    {
        foreach (self::classCandidates(canonical: $canonical, relative: $relative) as $fqcn) {
            try {
                $service = $container->get($fqcn);
                if (is_object($service) === true) {
                    return $service;
                }
            } catch (Throwable $e) {
                // This candidate is not registered — try the next name before
                // concluding the app is absent.
                continue;
            }
        }

        return null;

    }//end getService()


    /**
     * Whether a value is an instance of the named class under any candidate namespace.
     *
     * The listener-side counterpart to {@see self::classCandidates()}: a
     * listener registered under both names receives whichever event the other
     * app actually dispatches, so the type check has to accept both too.
     *
     * @param mixed  $value     The value to test, normally a dispatched event.
     * @param string $canonical Canonical (new) app name, e.g. 'filinq'.
     * @param string $relative  Class name below the app root, e.g. 'Event\SigningConcludedEvent'.
     *
     * @return bool True when $value is an instance of some candidate.
     * @spec exclude infrastructure utility with no feature requirement of its own; it is
     *   exercised through the features that call it
     */
    public static function isInstanceOf(mixed $value, string $canonical, string $relative): bool
    {
        foreach (self::classCandidates(canonical: $canonical, relative: $relative) as $fqcn) {
            if ($value instanceof $fqcn) {
                return true;
            }
        }

        return false;

    }//end isInstanceOf()


}//end class
