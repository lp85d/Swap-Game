<?php session_start(); ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Игра: Сбор элементов</title>
    <style>
        body { font-family: -apple-system, sans-serif; text-align: center; background: #f0f2f5; color: #1c1e21; }
        .container { background: white; max-width: 450px; margin: 40px auto; padding: 30px; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.1); }
        table { margin: 20px auto; border-spacing: 8px; }
        td { width: 50px; height: 50px; border: 2px solid #dee2e6; cursor: pointer; font-size: 22px; font-weight: bold; border-radius: 8px; transition: 0.2s; }
        td.selected { background: #007bff; color: white; border-color: #0056b3; transform: translateY(-2px); }
        button { padding: 12px 24px; font-size: 16px; cursor: pointer; border: none; border-radius: 8px; background: #28a745; color: white; font-weight: bold; width: 100%; }
        button:disabled { background: #bdc3c7; cursor: not-allowed; }
        #status { margin: 20px 0; font-weight: bold; padding: 12px; border-radius: 8px; border: 1px solid transparent; }
        .turn-my { background: #d4edda; color: #155724; border-color: #c3e6cb; }
        .turn-wait { background: #fff3cd; color: #856404; border-color: #ffeeba; }
        #msg { color: #7f8c8d; font-size: 13px; margin-top: 15px; }
    </style>
</head>
<body>
<div class="container">
    <h2>Сбор элементов</h2>
    <div id="setup">
        <p>Ваш IP: <strong><?= $_SERVER['REMOTE_ADDR'] ?></strong></p>
        <button id="readyBtn">Я готов к игре</button>
        <p style="font-size: 0.9em; color: #666;">Нужно минимум 2 игрока</p>
    </div>

    <div id="game" style="display:none;">
        <div id="status">Ожидание...</div>
        <table><tr id="row"></tr></table>
        <button id="swapBtn" disabled>Поменять местами</button>
    </div>
    <p id="msg"></p>
</div>

<script>
    let ws, myId, myCombination = [], isMyTurn = false, selected = [];
    const msg = document.getElementById('msg');
    const statusBox = document.getElementById('status');

    function connect() {
        ws = new WebSocket('wss://' + window.location.host + '/game/socket');
        
        ws.onopen = () => { msg.textContent = "Соединение активно"; };
        
        ws.onmessage = (e) => {
            const d = JSON.parse(e.data);
            console.log("Server message:", d); // Лог в браузер
            
            if (d.type === 'connected') {
                myId = d.id;
                console.log("Ваш внутренний ID:", myId);
            }
            
            if (d.type === 'start' || d.type === 'turn') {
                document.getElementById('setup').style.display = 'none';
                document.getElementById('game').style.display = 'block';
                
                myCombination = d.players[myId].combination;
                isMyTurn = (d.current_player === myId || d.player === myId);
                
                selected = [];
                render();
            }
            
            if (d.type === 'win') {
                alert(d.winner === myId ? "ПОБЕДА!" : "Игрок " + d.winner + " победил!");
                location.reload();
            }
        };

        ws.onclose = () => {
            msg.textContent = "Переподключение...";
            setTimeout(connect, 2000);
        };
    }

    function render() {
        const row = document.getElementById('row');
        row.innerHTML = '';
        
        myCombination.forEach((v, i) => {
            const td = document.createElement('td');
            td.textContent = v;
            if (selected.includes(i)) td.className = 'selected';
            
            td.onclick = () => {
                if (!isMyTurn) return;
                if (selected.includes(i)) {
                    selected = selected.filter(x => x !== i);
                } else if (selected.length < 2) {
                    selected.push(i);
                }
                document.getElementById('swapBtn').disabled = selected.length !== 2;
                render();
            };
            row.appendChild(td);
        });

        if (isMyTurn) {
            statusBox.textContent = "ВАШ ХОД";
            statusBox.className = "turn-my";
        } else {
            statusBox.textContent = "Ход соперника...";
            statusBox.className = "turn-wait";
        }
    }

    document.getElementById('readyBtn').onclick = function() {
        if (ws && ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({type: 'ready'}));
            this.disabled = true;
            this.textContent = "Ожидание...";
        }
    };

    document.getElementById('swapBtn').onclick = () => {
        if (selected.length === 2) {
            ws.send(JSON.stringify({type: 'swap', index1: selected[0], index2: selected[1]}));
            selected = [];
        }
    };

    connect();
</script>
</body>
</html>
