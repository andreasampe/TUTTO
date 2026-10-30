<?php
// DOMUS — API JSON. Tutte le chiamate passano da qui: api.php?r=risorsa/id
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
    $in = in_array($method, ['POST', 'PUT'], true) ? App::input() : [];
    $q = trim((string) ($_GET['q'] ?? ''));

    $res = match (true) {
        $resource === 'dashboard' && $method === 'GET' => Dashboard::data(),

        $resource === 'clients' && $id === null && $method === 'GET' => Clients::list($q),
        $resource === 'clients' && $id === null && $method === 'POST' => ['id' => Clients::create($in)],
        $resource === 'clients' && $id !== null && $method === 'GET' => Clients::get($id),
        $resource === 'clients' && $id !== null && $method === 'PUT' => (function () use ($id, $in) { Clients::update($id, $in); return Clients::get($id); })(),
        $resource === 'clients' && $id !== null && $method === 'DELETE' => (function () use ($id) { Clients::delete($id); return ['ok' => true]; })(),

        $route === 'projects/next-code' && $method === 'GET' => ['code' => Projects::nextCode()],
        $resource === 'projects' && $id === null && $method === 'GET' => Projects::list((string) ($_GET['status'] ?? ''), $q),
        $resource === 'projects' && $id === null && $method === 'POST' => ['id' => Projects::create($in)],
        $resource === 'projects' && $id !== null && $sub === 'phases' && $method === 'POST' => ['id' => Projects::addPhase($id, $in)],
        $resource === 'projects' && $id !== null && $method === 'GET' => Projects::get($id),
        $resource === 'projects' && $id !== null && $method === 'PUT' => (function () use ($id, $in) { Projects::update($id, $in); return Projects::get($id); })(),
        $resource === 'projects' && $id !== null && $method === 'DELETE' => (function () use ($id) { Projects::delete($id); return ['ok' => true]; })(),

        $resource === 'phases' && $id !== null && $method === 'PUT' => (function () use ($id, $in) { Projects::updatePhase($id, $in); return ['ok' => true]; })(),
        $resource === 'phases' && $id !== null && $method === 'DELETE' => (function () use ($id) { Projects::deletePhase($id); return ['ok' => true]; })(),

        $resource === 'tasks' && $id === null && $method === 'GET' => Tasks::list((string) ($_GET['filter'] ?? 'aperte')),
        $resource === 'tasks' && $id === null && $method === 'POST' => ['id' => Tasks::create($in)],
        $resource === 'tasks' && $id !== null && $method === 'PUT' => (function () use ($id, $in) { Tasks::update($id, $in); return ['ok' => true]; })(),
        $resource === 'tasks' && $id !== null && $method === 'DELETE' => (function () use ($id) { Tasks::delete($id); return ['ok' => true]; })(),

        $resource === 'journal' && $id === null && $method === 'POST' => ['id' => Projects::addJournal($user, $in)],
        $resource === 'journal' && $id !== null && $method === 'DELETE' => (function () use ($id) { Projects::deleteJournal($id); return ['ok' => true]; })(),

        $resource === 'settings' && $method === 'GET' => Settings::all(),
        $resource === 'settings' && $method === 'PUT' => Settings::save($in),

        $resource === 'export' && $method === 'GET' => (function () {
            header('Content-Disposition: attachment; filename="domus-backup-' . date('Y-m-d') . '.json"');
            return Backup::export();
        })(),

        default => throw new HttpError(404, 'Percorso inesistente'),
    };
    App::json($res);
} catch (\Throwable $e) {
    App::handleError($e);
}
