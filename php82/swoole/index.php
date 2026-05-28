<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<title>Swoole Chat - PHP 8.2</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: sans-serif; display: flex; height: 100vh; background: #f0f2f5; }
  #sidebar { width: 200px; background: #1e1e2e; color: #cdd6f4; padding: 16px; display: flex; flex-direction: column; gap: 12px; }
  #sidebar h3 { font-size: 13px; color: #a6adc8; text-transform: uppercase; }
  #client-list { list-style: none; font-size: 13px; }
  #client-list li { padding: 6px 0; cursor: pointer; border-radius: 4px; }
  #client-list li:hover { background: #313244; }
  #client-list li.me { color: #a6e3a1; font-weight: bold; cursor: default; }
  #client-list li.me:hover { background: transparent; }
  #status { font-size: 11px; margin-top: auto; }
  #main { flex: 1; display: flex; flex-direction: column; }
  #messages { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 8px; }
  .msg { padding: 8px 12px; border-radius: 8px; max-width: 70%; font-size: 14px; }
  .msg.chat { background: #fff; align-self: flex-start; }
  .msg.chat.mine { background: #a6e3a1; align-self: flex-end; }
  .msg.notice { background: transparent; color: #cc241d; font-size: 12px; align-self: center; font-weight: 500; }
  .msg.whisper { background: #f5c2e7; align-self: flex-start; font-style: italic; }
  .msg.whisper.mine { align-self: flex-end; background: #f5c2e7; opacity: 0.9; }
  .msg .meta { font-size: 11px; color: #888; margin-bottom: 2px; }
  #bottom { padding: 12px; background: #fff; border-top: 1px solid #ddd; display: flex; flex-direction: column; gap: 8px; }
  #controls { display: flex; gap: 8px; }
  #msg-input { flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
  #nickname-bar, #whisper-bar { display: flex; gap: 8px; }
  #nickname-input, #whisper-msg { flex: 1; padding: 6px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; }
  #whisper-target { width: 120px; padding: 6px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; }
  button { padding: 8px 14px; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; }
  #send-btn { background: #89b4fa; color: #fff; }
  #rename-btn { background: #cba6f7; color: #fff; }
  #whisper-btn { background: #f5c2e7; }
  #ping-btn { background: #f0f2f5; }
</style>
</head>
<body>

<div id="sidebar">
  <h3>접속자 (클릭시 귓속말)</h3>
  <ul id="client-list"></ul>
  <div id="status">⏳ 연결 중...</div>
</div>

<div id="main">
  <div id="messages"></div>
  <div id="bottom">
    <div id="nickname-bar">
      <input id="nickname-input" placeholder="닉네임 변경">
      <button id="rename-btn" onclick="rename()">변경</button>
    </div>
    <div id="whisper-bar">
      <input id="whisper-target" placeholder="상대방 닉네임">
      <input id="whisper-msg" placeholder="귓속말 내용" onkeydown="if(event.key==='Enter') whisper()">
      <button id="whisper-btn" onclick="whisper()">귓속말</button>
    </div>
    <div id="controls">
      <input id="msg-input" placeholder="메시지 입력" onkeydown="if(event.key==='Enter') sendChat()">
      <!-- <button id="ping-btn" onclick="ping()">Ping</button> -->
      <button id="send-btn" onclick="sendChat()">전송</button>
    </div>
  </div>
</div>

<script>
  let ws = null;
  let myFd = null;
  let myNickname = '';
  let pingInterval = null;

  if (!localStorage.getItem('uuid')) {
    localStorage.setItem('uuid', crypto.randomUUID());
  }
  const uuid = localStorage.getItem('uuid');
  const messages = document.getElementById('messages');
  const status   = document.getElementById('status');

  function connectWebSocket() {
    ws = new WebSocket('wss://localhost:9582');

    ws.onopen = () => {
      status.innerHTML = '🟢 연결됨';
      const savedNickname = localStorage.getItem('nickname');
      ws.send(JSON.stringify({ type: 'init', uuid, nickname: savedNickname || null }));

      clearInterval(pingInterval);
      pingInterval = setInterval(() => {
        if (ws.readyState === WebSocket.OPEN) {
          ws.send(JSON.stringify({ type: 'ping' }));
        }
      }, 20000);
    };

    ws.onclose = () => {
      status.innerHTML = '🔴 연결 끊김 (재연결 중...)';
      clearInterval(pingInterval);
      setTimeout(() => connectWebSocket(), 3000);
    };

    ws.onerror = (err) => {
      console.error(err);
      status.innerHTML = '❌ 오류 발생';
    };

    ws.onmessage = (e) => {
      const data = JSON.parse(e.data);

      switch (data.type) {
        case 'connected':
          myFd = data.fd;
          break;

        case 'init':
          myNickname = data.nickname;
          localStorage.setItem('nickname', data.nickname);
          document.getElementById('nickname-input').placeholder = data.nickname;
          addNotice(`✅ 연결 완료: ${data.nickname}`);
          updateClients(data.clients);
          break;

        case 'chat':
          addChat(data);
          break;

        case 'notice':
          addNotice(data.message);
          if (data.clients) updateClients(data.clients);
          break;

        case 'whisper':
          addWhisper(data);
          break;

        case 'pong':
          console.log(`🏓 Pong! (${data.time})`);
          break;
      }
    };
  }

  connectWebSocket();

  function sendChat() {
    const input = document.getElementById('msg-input');
    const msg = input.value.trim();
    if (!msg || !ws || ws.readyState !== WebSocket.OPEN) return;
    ws.send(JSON.stringify({ type: 'chat', message: msg }));
    input.value = '';
  }

  function rename() {
    const nickname = document.getElementById('nickname-input').value.trim();
    if (!nickname || !ws || ws.readyState !== WebSocket.OPEN) return;
    localStorage.setItem('nickname', nickname);
    ws.send(JSON.stringify({ type: 'rename', nickname }));
    document.getElementById('nickname-input').value = '';
    document.getElementById('nickname-input').placeholder = nickname;
  }

  function whisper() {
    const toNickname = document.getElementById('whisper-target').value.trim();
    const msg = document.getElementById('whisper-msg').value.trim();
    if (!toNickname || !msg || !ws || ws.readyState !== WebSocket.OPEN) return;
    
    // 본인에게 발송하는 것 방지
    if (toNickname === myNickname) {
      addNotice("❌ 자신에게는 귓속말을 보낼 수 없습니다.");
      return;
    }

    // 숫자가 아닌 닉네임(문자열) 그대로 전송
    ws.send(JSON.stringify({ type: 'whisper', to: toNickname, message: msg }));
    document.getElementById('whisper-msg').value = '';
  }

  // 접속자 명단 리스트에서 클릭 시 자동으로 귓속말 대상 지정 기능
  function setWhisperTarget(nickname) {
    if (nickname === myNickname) return;
    document.getElementById('whisper-target').value = nickname;
    document.getElementById('whisper-msg').focus();
  }

  function ping() {
    if (ws && ws.readyState === WebSocket.OPEN) ws.send(JSON.stringify({ type: 'ping' }));
  }

  function addChat(data) {
    const isMine = data.fd === myFd;
    const div = document.createElement('div');
    div.className = `msg chat${isMine ? ' mine' : ''}`;
    div.innerHTML = `<div class="meta">${isMine ? '나' : data.nickname} · ${data.time}</div>${data.message}`;
    messages.appendChild(div);
    messages.scrollTop = messages.scrollHeight;
  }

  function addNotice(text) {
    const div = document.createElement('div');
    div.className = 'msg notice';
    div.innerText = text;
    messages.appendChild(div);
    messages.scrollTop = messages.scrollHeight;
  }

  function addWhisper(data) {
    const div = document.createElement('div');
    // 내가 보낸 것과 받은 것의 정렬 구분
    div.className = `msg whisper${data.sent ? ' mine' : ''}`;
    div.innerHTML = `<div class="meta">🤫 ${data.sent ? `나 → ${data.to}` : `${data.from} 님이 보냄`} · ${data.time}</div>${data.message}`;
    messages.appendChild(div);
    messages.scrollTop = messages.scrollHeight;
  }

  function updateClients(clients) {
    const list = document.getElementById('client-list');
    list.innerHTML = clients.map(c => {
      if (c.fd === myFd) {
        return `<li class="me">${c.nickname} (나)</li>`;
      } else {
        // 일반 사용자는 클릭 시 귓속말 대상 지정 함수 호출하도록 추가
        return `<li onclick="setWhisperTarget('${c.nickname}')">${c.nickname}</li>`;
      }
    }).join('');
  }
</script>
</body>
</html>