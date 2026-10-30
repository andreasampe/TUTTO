<?php
// DOMUS — API JSON. Tutte le chiamate passano da qui: api.php?r=risorsa/id[/sotto-risorsa]
// (con il parametro "r" funziona su qualunque server, senza regole di riscrittura degli URL).
declare(strict_types=1);

namespace Domus;

require __DIR__ . '/src/App.php';

App::boot();

try {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');

    App::startSession();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $route = trim((string) ($_GET['r'] ?? ''), '/');
    $parts = $route === '' ? [] : explode('/', $route);
    $resource = $parts[0] ?? '';
    $id = isset($parts[1]) && ctype_digit($parts[1]) ? (int) $parts[1] : null;
    $sub = $parts[2] ?? null;

    if ($method !== 'GET') {
        // Il login e la configurazione iniziale avvengono prima di avere una sessione con token.
        if (!in_array($route, ['auth/login', 'auth/setup'], true)) {
            Auth::checkCsrf();
        } elseif (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'domus') {
            // Blocca i moduli inviati da altri siti verso login/setup.
            throw new HttpError(403, 'Richiesta non consentita');
        }
    }

    // Calendario iCal: si legge con il codice segreto personale, senza sessione.
    if ($route === 'calendar.ics' && $method === 'GET') {
        Ical::feed((string) ($_GET['token'] ?? ''));
    }

    // ---------- accesso (senza login) ----------
    if ($resource === 'auth') {
        $action = $parts[1] ?? '';
        $res = match ("$method $action") {
            'GET me' => [
                'user' => Auth::user(),
                'needs_setup' => Auth::needsSetup(),
                'csrf' => Auth::csrfToken(),
                'studio_name' => Settings::get('studio_name'),
                'app_name' => App::config('app_name'),
                'upload' => Files::limits(),
            ],
            'POST setup' => ['user' => Auth::setup(App::input()), 'csrf' => Auth::csrfToken()],
            'POST login' => ['user' => Auth::login(App::input()), 'csrf' => Auth::csrfToken()],
            'POST logout' => (function () { Auth::logout(); return ['ok' => true]; })(),
            'PUT profile' => ['user' => Auth::updateProfile(Auth::requireUser(), App::input())],
            default => throw new HttpError(404, 'Percorso inesistente'),
        };
        App::json($res);
    }

    // ---------- da qui in poi serve l'accesso ----------
    $user = Auth::requireUser();
    $isJson = str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json');
    $in = in_array($method, ['POST', 'PUT'], true) && $isJson ? App::input() : [];
    $q = trim((string) ($_GET['q'] ?? ''));
    $ok = static fn (callable $fn) => (function () use ($fn) { $fn(); return ['ok' => true]; })();
    $admin = static fn () => Auth::requireAdmin();

    $res = match (true) {
        $resource === 'dashboard' && $method === 'GET' => Dashboard::data($user),
        $resource === 'search' && $method === 'GET' => Search::run($q),
        $resource === 'calendar' && $method === 'GET' => Calendar::events($user, (string) ($_GET['from'] ?? ''), (string) ($_GET['to'] ?? '')),
        $resource === 'reports' && $method === 'GET' => Reports::data($user),
        $resource === 'gallery' && $method === 'GET' => Reports::gallery(),
        $route === 'ical' && $method === 'GET' => ['token' => Ical::token($user)],
        $route === 'ical' && $method === 'POST' => ['token' => Ical::token($user, true)],
        $resource === 'meta' && $method === 'GET' => [
            'permit_kinds' => Permits::KINDS,
            'file_categories' => Files::CATEGORIES,
            'plan_kinds' => Plans::KINDS,
        ],

        // clienti
        $resource === 'clients' && $id === null && $method === 'GET' => Clients::list($q),
        $resource === 'clients' && $id === null && $method === 'POST' => ['id' => Clients::create($in)],
        $resource === 'clients' && $id !== null && $method === 'GET' => Clients::get($id),
        $resource === 'clients' && $id !== null && $method === 'PUT' => (function () use ($id, $in) { Clients::update($id, $in); return Clients::get($id); })(),
        $resource === 'clients' && $id !== null && $method === 'DELETE' => $ok(fn () => Clients::delete($id)),

        // progetti
        $route === 'projects/next-code' && $method === 'GET' => ['code' => Projects::nextCode()],
        $resource === 'projects' && $id === null && $method === 'GET' => Projects::list((string) ($_GET['status'] ?? ''), $q),
        $resource === 'projects' && $id === null && $method === 'POST' => ['id' => Projects::create($in, $user)],
        $resource === 'projects' && $id !== null && $sub === 'phases' && $method === 'POST' => ['id' => Projects::addPhase($id, $in)],
        $resource === 'projects' && $id !== null && $sub === 'status' && $method === 'PUT' => $ok(fn () => Projects::setStatus($id, $in)),
        $resource === 'projects' && $id !== null && $sub === 'cover' && $method === 'PUT' => $ok(fn () => Projects::setCover($id, $in)),
        $resource === 'projects' && $id !== null && $sub === 'files' && $method === 'POST' => ['ids' => Files::upload($user, $id, $_FILES, (string) ($_POST['category'] ?? 'documento'))],
        $resource === 'projects' && $id !== null && $sub === null && $method === 'GET' => Projects::get($id, $user),
        $resource === 'projects' && $id !== null && $sub === null && $method === 'PUT' => (function () use ($id, $in, $user) { Projects::update($id, $in, $user); return Projects::get($id, $user); })(),
        $resource === 'projects' && $id !== null && $sub === null && $method === 'DELETE' => $ok(fn () => Projects::delete($id)),

        // fasi
        $resource === 'phases' && $id !== null && $method === 'PUT' => $ok(fn () => Projects::updatePhase($id, $in)),
        $resource === 'phases' && $id !== null && $method === 'DELETE' => $ok(fn () => Projects::deletePhase($id)),

        // scadenze
        $resource === 'tasks' && $id === null && $method === 'GET' => Tasks::list((string) ($_GET['filter'] ?? 'aperte')),
        $resource === 'tasks' && $id === null && $method === 'POST' => ['id' => Tasks::create($in)],
        $resource === 'tasks' && $id !== null && $method === 'PUT' => $ok(fn () => Tasks::update($id, $in)),
        $resource === 'tasks' && $id !== null && $method === 'DELETE' => $ok(fn () => Tasks::delete($id)),

        // diario
        $resource === 'journal' && $id === null && $method === 'POST' => ['id' => Projects::addJournal($user, $in)],
        $resource === 'journal' && $id !== null && $method === 'DELETE' => $ok(fn () => Projects::deleteJournal($id)),

        // documenti
        $resource === 'files' && $id !== null && $method === 'GET' => Files::send($id, ($_GET['inline'] ?? '') === '1'),
        $resource === 'files' && $id !== null && $method === 'PUT' => $ok(fn () => Files::update($id, $in)),
        $resource === 'files' && $id !== null && $method === 'DELETE' => $ok(fn () => Files::delete($id)),

        // planimetrie (area dedicata): ritocchi e quotatura avvengono nel browser, qui i file e le versioni
        $resource === 'plans' && $id === null && $method === 'GET' => Plans::list(isset($_GET['project']) ? (int) $_GET['project'] : null, $q),
        $resource === 'plans' && $id === null && $method === 'POST' => ['id' => Plans::create($user, $in)],
        $resource === 'plans' && $id !== null && $sub === 'versions' && $method === 'POST' => Plans::addVersion($user, $id, $_FILES, (string) ($_POST['note'] ?? ''), (string) ($_POST['report'] ?? '')),
        $resource === 'plans' && $id !== null && $sub === null && $method === 'GET' => Plans::get($id),
        $resource === 'plans' && $id !== null && $sub === null && $method === 'PUT' => (function () use ($id, $in) { Plans::update($id, $in); return Plans::get($id); })(),
        $resource === 'plans' && $id !== null && $sub === null && $method === 'DELETE' => $ok(fn () => Plans::delete($id)),

        // pratiche edilizie
        $resource === 'permits' && $id === null && $method === 'POST' => ['id' => Permits::create($in)],
        $resource === 'permits' && $id !== null && $method === 'PUT' => $ok(fn () => Permits::update($id, $in)),
        $resource === 'permits' && $id !== null && $method === 'DELETE' => $ok(fn () => Permits::delete($id)),

        // ore lavorate
        $resource === 'hours' && $id === null && $method === 'POST' => ['id' => Hours::create($user, $in)],
        $resource === 'hours' && $id !== null && $method === 'DELETE' => $ok(fn () => Hours::delete($user, $id)),

        // ---------- solo amministratore ----------
        $resource === 'projects' && $id !== null && $sub === 'quote' && $method === 'GET' => $admin() ? Payments::quotePdf($id) : null,
        $resource === 'payments' && $id === null && $method === 'POST' => $admin() ? ['id' => Payments::create($in)] : null,
        $resource === 'payments' && $id !== null && $method === 'PUT' => $admin() ? $ok(fn () => Payments::update($id, $in)) : null,
        $resource === 'payments' && $id !== null && $method === 'DELETE' => $admin() ? $ok(fn () => Payments::delete($id)) : null,

        $resource === 'users' && $id === null && $method === 'GET' => $admin() ? Users::list() : null,
        $resource === 'users' && $id === null && $method === 'POST' => $admin() ? ['id' => Users::create($in)] : null,
        $resource === 'users' && $id !== null && $method === 'PUT' => $admin() ? $ok(fn () => Users::update($user, $id, $in)) : null,

        $route === 'demo' && $method === 'GET' => ['available' => Demo::available()],
        $route === 'demo' && $method === 'POST' => $admin() ? Demo::seed($user) : null,

        $resource === 'settings' && $method === 'GET' => Settings::all(),
        $resource === 'settings' && $method === 'PUT' => $admin() ? Settings::save($in) : null,

        $resource === 'export' && $method === 'GET' => $admin() ? (function () {
            header('Content-Disposition: attachment; filename="domus-backup-' . date('Y-m-d') . '.json"');
            return Backup::export();
        })() : null,

        default => throw new HttpError(404, 'Percorso inesistente'),
    };
    App::json($res);
} catch (\Throwable $e) {
    App::handleError($e);
}
