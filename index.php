<?php session_start(); ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Игра: Сбор элементов</title>
    <style>
        body { 
            font-family: -apple-system, sans-serif; 
            text-align: center; 
            background: #f0f2f5; 
            color: #1c1e21; 
            margin: 0;
            padding: 20px;
        }
        .container { 
            background: white; 
            max-width: 600px; 
            margin: 40px auto; 
            padding: 30px; 
            border-radius: 12px; 
            box-shadow: 0 8px 24px rgba(0,0,0,0.1); 
        }
        table { 
            margin: 20px auto; 
            border-spacing: 8px; 
        }
        td { 
            width: 60px; 
            height: 60px; 
            border: 2px solid #dee2e6; 
            cursor: pointer; 
            font-size: 24px; 
            font-weight: bold; 
            border-radius: 8px; 
            transition: 0.2s; 
        }
        td.selected { 
            background: #007bff; 
            color: white; 
            border-color: #0056b3; 
            transform: translateY(-2px); 
            box-shadow: 0 4px 8px rgba(0,123,255,0.3);
        }
        td.inactive {
            cursor: not-allowed;
            opacity: 0.7;
        }
        td.inactive:hover {
            background: none;
            transform: none;
        }
        button { 
            padding: 12px 24px; 
            font-size: 16px; 
            cursor: pointer; 
            border: none; 
            border-radius: 8px; 
            background: #28a745; 
            color: white; 
            font-weight: bold; 
            width: 100%; 
            margin: 5px 0;
            transition: 0.2s;
        }
        button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40,167,69,0.3);
        }
        button:disabled { 
            background: #bdc3c7 !important; 
            cursor: not-allowed; 
            transform: none;
            box-shadow: none;
            opacity: 0.7;
        }
        #status { 
            margin: 20px 0; 
            font-weight: bold; 
            padding: 12px; 
            border-radius: 8px; 
            border: 1px solid transparent; 
        }
        .turn-my { 
            background: #d4edda; 
            color: #155724; 
            border-color: #c3e6cb; 
        }
        .turn-wait { 
            background: #fff3cd; 
            color: #856404; 
            border-color: #ffeeba; 
        }
        #msg { 
            color: #7f8c8d; 
            font-size: 13px; 
            margin-top: 15px; 
        }
        .info {
            background: #e7f3ff;
            border-radius: 8px;
            padding: 10px;
            margin: 10px 0;
            color: #004085;
            border: 1px solid #b8daff;
        }
        #goal {
            font-family: monospace;
            font-size: 18px;
            color: #6c757d;
        }
        .difficulty-badge {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            margin: 10px 0;
        }
        #currentTurn {
            font-size: 18px;
            margin: 10px 0;
        }
        .opponent-turn {
            background: #e9ecef;
            color: #6c757d;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>🎮 Сбор элементов</h2>
    
    <div id="setup">
        <div class="info">
            <p>Ваш IP: <strong><?= $_SERVER['REMOTE_ADDR'] ?></strong></p>
            <p>Минимум игроков: <strong>2</strong></p>
        </div>
        <button id="readyBtn" class="ready-btn">✅ Я готов к игре</button>
        <p style="font-size: 0.9em; color: #666;">Нажмите кнопку, когда будете готовы</p>
    </div>

    <div id="game" style="display:none;">
        <div id="difficultyInfo" class="difficulty-badge"></div>
        <div id="goal"></div>
        <div id="status">Ожидание...</div>
        <div id="currentTurn"></div>
        <div style="overflow-x: auto;">
            <table>
                <tbody id="grid"></tbody>
            </table>
        </div>
        <button id="swapBtn" disabled>🔄 Поменять местами</button>
        <button id="resetSelectionBtn" style="background: #6c757d;" disabled>↺ Сбросить выбор</button>
    </div>
    
    <p id="msg"></p>
</div>

<script>
    let ws, myId, myCombination = [], isMyTurn = false, selected = [];
    let gameDifficulty = 3, gameSize = 3;
    let gameStarted = false;
    let players = {};
    
    const msg = document.getElementById('msg');
    const statusBox = document.getElementById('status');
    const goalDiv = document.getElementById('goal');
    const difficultyInfo = document.getElementById('difficultyInfo');
    const currentTurnDiv = document.getElementById('currentTurn');
    const resetBtn = document.getElementById('resetSelectionBtn');
    const swapBtn = document.getElementById('swapBtn');

    function connect() {
        // Определяем протокол (wss для HTTPS, ws для HTTP)
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsUrl = `${protocol}//${window.location.host}/game/socket`;
        
        ws = new WebSocket(wsUrl);
        
        ws.onopen = () => { 
            msg.textContent = "✅ Соединение активно"; 
            console.log("Connected to server");
        };
        
        ws.onmessage = (e) => {
            const d = JSON.parse(e.data);
            console.log("Server message:", d);
            
            if (d.type === 'connected') {
                myId = d.id;
                gameDifficulty = d.difficulty || 3;
                gameSize = d.gameSize || 3;
                console.log(`Your ID: ${myId}, Difficulty: ${gameDifficulty}`);
                
                difficultyInfo.textContent = `Сложность: ${gameDifficulty} цифр`;
            }
            
            if (d.type === 'start') {
                gameStarted = true;
                players = d.players;
                myCombination = d.players[myId].combination;
                gameDifficulty = d.difficulty || 3;
                gameSize = d.gameSize || 3;
                
                isMyTurn = (d.current_player === myId);
                
                document.getElementById('setup').style.display = 'none';
                document.getElementById('game').style.display = 'block';
                
                difficultyInfo.textContent = `Сложность: ${gameDifficulty} цифр`;
                goalDiv.innerHTML = `🎯 Цель: <strong>[${d.players[Object.keys(d.players)[0]].combination.map((_, i) => i).join(', ')}]</strong> (в любом порядке)`;
                
                selected = [];
                updateUI();
            }
            
            if (d.type === 'turn') {
                players = d.players;
                isMyTurn = (d.player === myId);
                if (d.players[myId]) {
                    myCombination = d.players[myId].combination;
                }
                
                selected = [];
                updateUI();
                
                if (isMyTurn) {
                    statusBox.textContent = "🎲 ВАШ ХОД";
                }
            }
            
            if (d.type === 'win') {
                const winner = d.winner;
                if (winner === myId) {
                    alert("🏆 ПОБЕДА! Вы собрали правильную комбинацию!");
                } else {
                    alert(`😢 Игрок ${winner} победил!`);
                }
                setTimeout(() => location.reload(), 2000);
            }
        };

        ws.onclose = () => {
            msg.textContent = "🔄 Переподключение...";
            setTimeout(connect, 2000);
        };
        
        ws.onerror = (error) => {
            console.error("WebSocket error:", error);
            msg.textContent = "❌ Ошибка соединения";
        };
    }

    function updateUI() {
        renderGrid();
        updateTurnInfo();
        updateSwapButton();
        updateResetButton();
    }

    function renderGrid() {
        const grid = document.getElementById('grid');
        grid.innerHTML = '';
        
        // Рассчитываем количество строк (квадратное поле)
        const size = Math.ceil(Math.sqrt(gameDifficulty));
        
        for (let row = 0; row < size; row++) {
            const tr = document.createElement('tr');
            for (let col = 0; col < size; col++) {
                const index = row * size + col;
                const td = document.createElement('td');
                
                if (index < gameDifficulty) {
                    td.textContent = myCombination[index] !== undefined ? myCombination[index] : '';
                    
                    if (selected.includes(index)) {
                        td.className = 'selected';
                    }
                    
                    // Если не наш ход - ячейки неактивны
                    if (!isMyTurn) {
                        td.classList.add('inactive');
                        td.onclick = null;
                    } else {
                        td.onclick = () => {
                            if (!isMyTurn || !gameStarted) return;
                            
                            if (selected.includes(index)) {
                                selected = selected.filter(x => x !== index);
                            } else if (selected.length < 2) {
                                selected.push(index);
                            }
                            
                            // Сортируем выбранные индексы для удобства
                            selected.sort((a, b) => a - b);
                            
                            updateSwapButton();
                            updateResetButton();
                            renderGrid();
                        };
                    }
                } else {
                    td.textContent = '';
                    td.style.background = '#f8f9fa';
                    td.style.border = '1px dashed #dee2e6';
                    td.style.cursor = 'default';
                }
                
                tr.appendChild(td);
            }
            grid.appendChild(tr);
        }
    }

    function updateSwapButton() {
        // Кнопка меняется местами становится серой (disabled) в двух случаях:
        // 1. Не наш ход
        // 2. Наш ход, но не выбрано 2 ячейки
        if (!isMyTurn) {
            swapBtn.disabled = true;
            swapBtn.textContent = '⏳ Ход соперника...';
            swapBtn.style.background = '#bdc3c7';
        } else if (selected.length === 2) {
            swapBtn.disabled = false;
            swapBtn.textContent = `🔄 Поменять ${selected[0]+1} и ${selected[1]+1}`;
            swapBtn.style.background = '#28a745';
        } else {
            swapBtn.disabled = true;
            swapBtn.textContent = '🔄 Поменять местами';
            swapBtn.style.background = '#bdc3c7';
        }
    }

    function updateResetButton() {
        // Кнопка сброса активна только когда есть выбранные ячейки И это наш ход
        resetBtn.disabled = !isMyTurn || selected.length === 0;
    }

    function updateTurnInfo() {
        if (isMyTurn) {
            statusBox.textContent = "🎲 ВАШ ХОД";
            statusBox.className = "turn-my";
            
            // Находим соперника
            let opponentName = "соперник";
            for (let id in players) {
                if (id != myId) {
                    opponentName = `игрок ${id}`;
                    break;
                }
            }
            currentTurnDiv.innerHTML = `👤 Ваш ход, ${opponentName} ожидает`;
        } else {
            statusBox.textContent = "⏳ Ход соперника";
            statusBox.className = "turn-wait";
            
            // Находим кто сейчас ходит
            let currentPlayer = "соперник";
            for (let id in players) {
                // Здесь должна быть логика определения текущего игрока
                // Пока просто показываем общую информацию
            }
            currentTurnDiv.innerHTML = `👤 Сейчас ходит соперник, ожидайте...`;
        }
    }

    // Обработчики кнопок
    document.getElementById('readyBtn').onclick = function() {
        if (ws && ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({type: 'ready'}));
            this.disabled = true;
            this.textContent = "⏳ Ожидание других игроков...";
            msg.textContent = "Ищем соперников...";
        }
    };

    swapBtn.onclick = () => {
        if (selected.length === 2 && isMyTurn && gameStarted) {
            ws.send(JSON.stringify({
                type: 'swap', 
                index1: selected[0], 
                index2: selected[1]
            }));
            selected = [];
            updateUI();
        }
    };

    resetBtn.onclick = () => {
        selected = [];
        updateUI();
    };

    // Запускаем соединение
    connect();
</script>
</body>
</html>
