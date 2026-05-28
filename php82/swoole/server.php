<?php

use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

$server = new Server("0.0.0.0", 9582);

$server->set([
    'worker_num'               => 1,
    'heartbeat_check_interval' => 10,
    'heartbeat_idle_time'      => 30,
]);

$clients = []; // fd => 접속자 정보
$uuidMap = []; // uuid => nickname

$server->on('open', function (Server $server, Request $request) use (&$clients) {
    $fd = $request->fd;
    $clients[$fd] = [
        'fd'       => $fd,
        'uuid'     => null,
        'nickname' => "Guest#{$fd}",
        'joined'   => date('H:i:s'),
    ];

    echo "[open] #{$fd} 연결\n";

    $server->push($fd, json_encode([
        'type'    => 'connected',
        'version' => 'PHP 8.2',
        'fd'      => $fd,
    ]));
});

$server->on('message', function (Server $server, Frame $frame) use (&$clients, &$uuidMap) {
    $fd   = $frame->fd;
    $data = json_decode($frame->data, true);

    if (!$data || !isset($data['type'])) return;

    switch ($data['type']) {
        case 'init':
            $uuid          = $data['uuid'] ?? null;
            $savedNickname = $uuid ? ($uuidMap[$uuid] ?? null) : null;
            $nickname      = $savedNickname ?? ("Guest#{$fd}");

            $clients[$fd]['uuid']     = $uuid;
            $clients[$fd]['nickname'] = $nickname;
            if ($uuid) $uuidMap[$uuid] = $nickname;

            echo "[init] #{$fd} uuid={$uuid} nickname={$nickname}\n";

            $server->push($fd, json_encode([
                'type'     => 'init',
                'nickname' => $nickname,
                'clients'  => array_values($clients),
            ]));

            broadcastExcept($server, $clients, $fd, [
                'type'    => 'notice',
                'message' => "{$nickname} 님이 입장했습니다.",
                'clients' => array_values($clients),
            ]);
            break;

        case 'rename':
            $old = $clients[$fd]['nickname'] ?? "Guest#{$fd}";
            $new = htmlspecialchars(trim($data['nickname'] ?? ''));
            if (!$new) break;

            // 중복 닉네임 방지 (선택 사항이지만 고유 ID 대용이므로 안전장치 추가)
            foreach ($clients as $c) {
                if ($c['fd'] !== $fd && $c['nickname'] === $new) {
                    $server->push($fd, json_encode([
                        'type'    => 'notice',
                        'message' => "❌ 이미 사용 중인 닉네임입니다.",
                    ]));
                    break 2;
                }
            }

            $clients[$fd]['nickname'] = $new;
            $uuid = $clients[$fd]['uuid'] ?? null;
            if ($uuid) $uuidMap[$uuid] = $new;

            echo "[rename] #{$fd} {$old} → {$new}\n";
            broadcastAll($server, $clients, [
                'type'    => 'notice',
                'message' => "{$old} 님이 {$new} 으로 닉네임을 변경했습니다.",
                'clients' => array_values($clients),
            ]);
            break;

        case 'chat':
            $msg = htmlspecialchars(trim($data['message'] ?? ''));
            if (!$msg) break;
            echo "[chat] {$clients[$fd]['nickname']}: {$msg}\n";
            broadcastAll($server, $clients, [
                'type'     => 'chat',
                'fd'       => $fd,
                'nickname' => $clients[$fd]['nickname'] ?? "Guest#{$fd}",
                'message'  => $msg,
                'time'     => date('H:i:s'),
            ]);
            break;

        case 'whisper':
            $targetNickname = trim($data['to'] ?? '');
            $msg            = htmlspecialchars(trim($data['message'] ?? ''));
            if (!$msg || !$targetNickname) break;

            // 1. 대상 닉네임이 현재 접속 중인지 존재 여부 체크
            $targetFd = null;
            foreach ($clients as $c) {
                if ($c['nickname'] === $targetNickname) {
                    $targetFd = $c['fd'];
                    break;
                }
            }

            // 2. 존재하지 않는 경우 본인에게 에러 알림 전송
            if ($targetFd === null || !$server->isEstablished($targetFd)) {
                $server->push($fd, json_encode([
                    'type'    => 'notice',
                    'message' => "❌ 귓속말 실패: '{$targetNickname}' 님은 현재 접속 중이 아닙니다.",
                ]));
                break;
            }

            // 3. 존재하는 경우 정상 발송
            echo "[whisper] {$clients[$fd]['nickname']} → {$targetNickname}\n";
            $payload = [
                'type'    => 'whisper',
                'from'    => $clients[$fd]['nickname'] ?? "Guest#{$fd}",
                'to'      => $targetNickname,
                'message' => $msg,
                'time'    => date('H:i:s'),
            ];
            
            $server->push($targetFd, json_encode($payload));
            $server->push($fd, json_encode($payload + ['sent' => true]));
            break;

        case 'ping':
            $server->push($fd, json_encode(['type' => 'pong', 'time' => date('H:i:s')]));
            break;
    }
});

$server->on('close', function (Server $server, int $fd) use (&$clients) {
    if (isset($clients[$fd])) {
        $nickname = $clients[$fd]['nickname'] ?? "#{$fd}";
        unset($clients[$fd]);
        echo "[close] #{$fd} 종료\n";
        broadcastAll($server, $clients, [
            'type'    => 'notice',
            'message' => "{$nickname} 님이 퇴장했습니다.",
            'clients' => array_values($clients),
        ]);
    }
});

function broadcastExcept(Server $server, array $clients, int $exceptFd, array $payload): void {
    $json = json_encode($payload);
    foreach (array_keys($clients) as $fd) {
        if ($fd !== $exceptFd && $server->isEstablished($fd)) {
            $server->push($fd, $json);
        }
    }
}

function broadcastAll(Server $server, array $clients, array $payload): void {
    $json = json_encode($payload);
    foreach (array_keys($clients) as $fd) {
        if ($server->isEstablished($fd)) {
            $server->push($fd, $json);
        }
    }
}

$server->start();