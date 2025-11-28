<?php

// 1. PHP 세션 시
session_start();
$voter_session_id = session_id(); // 현재 세션 ID

// 2. DB 연결 설정 파일을 불러옵니다.
include 'db_connect.php'; 

// 2. FA 대상 선수 목록을 하드코딩
$fa_player_ids = [13168, 10344]; // 하드코딩된 후보 2명
$fa_players = [
    13168 => ['name' => '강백호', 'team' => 'KT', 'img_path' => 'kang.png'],
    10344 => ['name' => '최형우', 'team' => 'KIA', 'img_path' => 'choi.png']
];

// 3. 현재 선택된 선수 ID 결정 (GET 파라미터 또는 기본값)
if (isset($_GET['player_id']) && in_array((int)$_GET['player_id'], $fa_player_ids)) {
    $current_player_id = intval($_GET['player_id']);
} else {
    // URL에 ID가 없으면 첫 번째 선수를 기본값으로 설정
    $current_player_id = $fa_player_ids[0]; 
}

// 4. A. 현재 선택된 FA 선수 정보 상세 조회
$player_query = "SELECT P.name, T.team_name, PD.pos_name, P.birth_date, P.career, P.signing_bonus, 
                 S.amount AS base_salary
                 FROM player P
                 JOIN team T ON P.team_id = T.team_id
                 LEFT JOIN position_detail PD ON P.pos_id = PD.pos_id
                 LEFT JOIN salary S ON P.player_id = S.player_id 
                 WHERE P.player_id = ?
                   AND S.season_id = (
                       SELECT MAX(season_id) 
                       FROM salary 
                       WHERE player_id = P.player_id
                   )";

// 직전 연봉을 받아올 때 서브쿼리가 연봉 기록이 없는 경우 (NULL)를 처리할 수 있도록, LEFT JOIN

$stmt_player = $conn->prepare($player_query);
$stmt_player->bind_param("i", $current_player_id);
$stmt_player->execute();

if ($conn->error) {
    die("DB 쿼리 실행 중 오류 발생: " . $conn->error . "<br>쿼리: " . $player_query);
}

$result_player = $stmt_player->get_result();
$current_player_db = $result_player->fetch_assoc();
$stmt_player->close();

if (!$current_player_db) {
    die("선수 정보를 찾을 수 없습니다. player_id=" . $current_player_id);
}

// 5. 선수 기본 정보 배열 병합 (하드코딩된 이름/팀/사진 + DB 정보)
$current_player = array_merge($current_player_db, $fa_players[$current_player_id]);

// 6. 사용자 입력 변수 초기값 설정 및 기존 투표 기록 확인
$user_vote_amount = 0;
$average_salary = 0;
$total_votes = 0;
$message = "";

// 현재 세션의 투표 기록 유무를 확인하는 변수
$has_existing_vote = false; 

try {
    // 현재 세션의 투표 기록이 있는지 확인 및 가져오기
    $check_vote_query = "SELECT voted_amount FROM fa_vote WHERE player_id = ? AND voter_session_id = ?";
    $stmt_check = $conn->prepare($check_vote_query);
    $stmt_check->bind_param("is", $current_player_id, $voter_session_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    if ($row_check = $result_check->fetch_assoc()) {
         $user_vote_amount = $row_check['voted_amount'];
         $has_existing_vote = true; // 기록 있음
    }
    $stmt_check->close();

    // 초기 투표 평균 조회
    $initial_avg_query = "SELECT AVG(voted_amount) AS avg_sal, COUNT(*) AS total_count FROM fa_vote WHERE player_id = ?";
    $stmt_initial_avg = $conn->prepare($initial_avg_query);
    $stmt_initial_avg->bind_param("i", $current_player_id);
    $stmt_initial_avg->execute();
    $result_initial_avg = $stmt_initial_avg->get_result();

    if ($row = $result_initial_avg->fetch_assoc()) {
        $average_salary = round($row['avg_sal']);
        $total_votes = $row['total_count'];
    }
    $stmt_initial_avg->close();
    
} catch (mysqli_sql_exception $exception) {
     $message = "초기 투표 정보 조회 중 오류가 발생했습니다: " . $exception->getMessage();
}

// 버튼 텍스트 설정
$action_button_text = $has_existing_vote ? "투표 수정 (UPDATE)" : "투표 추가 (INSERT)";


// 7. 투표 처리 및 평균 계산 (POST 요청)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 7-1. 삭제 액션 처리
    if (isset($_POST['action']) && $_POST['action'] === 'delete_vote') {
        $voted_player_id = intval($_POST['player_id']);
        $conn->begin_transaction();

        try {
            // DELETE: 현재 세션의 투표 기록 삭제
            $delete_query = "DELETE FROM fa_vote WHERE player_id = ? AND voter_session_id = ?";
            $stmt_delete = $conn->prepare($delete_query);
            $stmt_delete->bind_param("is", $voted_player_id, $voter_session_id);
            $stmt_delete->execute();
            $stmt_delete->close();
            
            // 삭제 후 투표 평균 재계산
            $avg_query = "SELECT AVG(voted_amount) AS avg_sal, COUNT(*) AS total_count FROM fa_vote WHERE player_id = ?";
            $stmt_avg = $conn->prepare($avg_query);
            $stmt_avg->bind_param("i", $voted_player_id);
            $stmt_avg->execute();
            $result_avg = $stmt_avg->get_result();
            if ($row = $result_avg->fetch_assoc()) {
                $average_salary = round($row['avg_sal']); 
                $total_votes = $row['total_count'];
            }
            $stmt_avg->close();
            
            $conn->commit();
            $message = "투표 기록이 성공적으로 삭제되었습니다.";
            $user_vote_amount = 0; // 삭제 후 입력 필드 비움
            $has_existing_vote = false; // 상태 업데이트
            $action_button_text = "투표 추가 (INSERT)"; // 버튼 텍스트 업데이트
            
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $message = "투표 삭제 처리 중 오류가 발생했습니다: " . $exception->getMessage();
        }
    
    // 7-2. 추가/수정 액션 처리
    } elseif (isset($_POST['player_id']) && isset($_POST['vote_amount']) && is_numeric($_POST['vote_amount'])) {
        $voted_player_id = intval($_POST['player_id']);
        $user_vote_amount = intval($_POST['vote_amount']);
        $current_datetime = date('Y-m-d H:i:s'); 

        $conn->begin_transaction();
        
        try {
            // INSERT/UPDATE: ON DUPLICATE KEY UPDATE를 사용하여 추가와 수정을 동시에 처리
            $insert_query = "INSERT INTO fa_vote (player_id, voted_amount, vote_date, voter_session_id) 
                             VALUES (?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE voted_amount = VALUES(voted_amount), vote_date = VALUES(vote_date)";
            $stmt_insert = $conn->prepare($insert_query);
            $stmt_insert->bind_param("iiss", $voted_player_id, $user_vote_amount, $current_datetime, $voter_session_id);
            $stmt_insert->execute();
            $stmt_insert->close();

            // AVG()
            $avg_query = "SELECT AVG(voted_amount) AS avg_sal, COUNT(*) AS total_count FROM fa_vote WHERE player_id = ?";
            $stmt_avg = $conn->prepare($avg_query);
            $stmt_avg->bind_param("i", $voted_player_id);
            $stmt_avg->execute();
            $result_avg = $stmt_avg->get_result();
            
            if ($row = $result_avg->fetch_assoc()) {
                $average_salary = round($row['avg_sal']); 
                $total_votes = $row['total_count'];
            }
            $stmt_avg->close();

            $conn->commit();
            
            // 메시지 및 상태 업데이트
            $action_type = $has_existing_vote ? "수정" : "추가";
            $message = "투표 기록이 성공적으로 {$action_type}되었습니다!";
            $has_existing_vote = true; // 투표했으니 기록이 있는 상태로 변경
            $action_button_text = "투표 수정 (UPDATE)";
            

        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $message = "투표 처리 중 오류가 발생했습니다: " . $exception->getMessage();
        }
    } else {
        $message = "유효한 연봉 금액을 입력해 주세요.";
    }
}

// 8. DB 연결 종료
$conn->close();
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KBO 선수 연봉 투표</title>
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            margin: 0; 
            background-color: #121212; 
            color: #E0E0E0; 
        }
        .nav-bar {
            background-color: #212121; 
            padding: 15px 0;
            border-bottom: 1px solid #333333;
        }
        .nav-link {
            color: #CCCCCC; 
            text-decoration: none; 
            padding: 0 15px; 
            transition: color 0.3s, border-bottom 0.3s;
        }
        .nav-link:hover {
            color: #FFFFFF; 
        }
        .nav-link.active {
            color: #64ffda; 
            font-weight: bold; 
            border-bottom: 3px solid #64ffda;
        }
        .container { 
            max-width: 1100px; 
            margin: 30px auto; 
            background: #212121; 
            padding: 30px; 
            border-radius: 8px; 
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.5); 
        }
        h2 { 
            color: #FFFFFF; 
            border-bottom: 2px solid #333333; 
            padding-bottom: 10px; 
            margin-bottom: 20px; 
        }

        /* FA 투표 섹션 스타일 */
        .vote-section {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-top: 30px;
        }

        /* 선수 정보 박스 */
        .player-info-box {
            background-color: #2c2c2c;
            padding: 20px;
            border-radius: 6px;
            width: 50%;
        }

        /* 연봉 입력 및 투표 결과 박스 */
        .vote-input-box {
            background-color: #2c2c2c;
            padding: 20px;
            border-radius: 6px;
            width: 45%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .player-info-detail {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 10px;
            font-size: 0.95em;
        }
        .player-info-detail strong {
            color: #CCCCCC;
        }

        .player-card-list {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 30px;
        }

        .player-card {
            width: 200px;
            height: 270px;
            background-color: #333333;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
        }
        .player-card.active {
             border: 3px solid #64ffda;
             box-shadow: 0 0 10px #64ffda;
        }
        .player-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .vote-amount-display {
            text-align: right;
            font-size: 3em;
            color: #64ffda;
            font-weight: bold;
            margin: 10px 0;
            border-bottom: 2px solid #333333;
            padding-bottom: 5px;
        }

        input[type="number"] {
            padding: 10px; 
            border: 1px solid #555555; 
            border-radius: 4px; 
            margin-top: 10px;
            background-color: #333333; 
            color: #FFFFFF;
            width: 100%;
            box-sizing: border-box; 
        }
        
        .vote-actions {
            display: flex;
            justify-content: flex-start; /* 버튼 3개가 될 경우를 대비해 flex-start 사용 */
            gap: 10px;
            margin-top: 20px;
        }

        .vote-actions button {
            padding: 12px 20px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-weight: bold;
            flex-grow: 1;
        }

        .btn-submit { 
            background-color: #64ffda; 
            color: #121212; 
        }
        .btn-reset { 
            background-color: #444444; 
            color: #FFFFFF; 
        }
        
        /* 투표 삭제 버튼 스타일 */
        .btn-delete { 
            background-color: #d32f2f;
            color: #FFFFFF; 
        }


        .vote-message {
            background-color: #333333;
            color: #FFFFFF;
            padding: 15px;
            border-radius: 6px;
            margin-top: 20px;
            text-align: center;
            font-size: 1.1em;
        }

        .average-result-box {
            background-color: #1e1e1e;
            padding: 20px;
            margin-top: 30px;
            border-radius: 6px;
            text-align: center;
            border: 2px solid #64ffda;
        }
        .average-result-box h3 {
             color: #64ffda;
             margin-top: 0;
             border-bottom: 1px solid #333333;
             padding-bottom: 10px;
             margin-bottom: 15px;
        }
        .average-result-box p {
            font-size: 1.2em;
            font-weight: 500;
        }
        .average-salary-text {
            font-size: 2.5em;
            font-weight: bold;
            color: #64ffda;
            display: block;
            margin: 10px 0;
        }
        
        .nav-arrow {
            background: rgba(0,0,0,0.5);
            color: white;
            padding: 10px;
            border-radius: 50%;
            align-self: center;
            cursor: pointer;
            z-index: 10;
        }
        
    </style>
</head>
<body>

<nav class="nav-bar">
    <div style="max-width: 1100px; margin: auto; display: flex; justify-content: space-between;">
        <a href="/team17/cost_efficiency_rank.php" class="nav-link">가성비 랭킹</a>
        <a href="/team17/player_detail.php" class="nav-link">선수별 페이지</a>
        <a href="/team17/fa_vote.php" class="nav-link active">선수 연봉 투표</a>
        <a href="/team17/player_growth.php" class="nav-link">선수 성장 추이</a>
        <a href="/team17/analysis_aggregate.php" class="nav-link">팀/포지션별 연봉</a>
        <a href="/team17/analysis_rollup.php" class="nav-link">연봉 계층별 효율</a>
        <a href="/team17/attack_stat.php" class="nav-link">타격 기록</a>
        <a href="/team17/defense_stat.php" class="nav-link"> 수비 기록</a>
    </div>
</nav>

<div class="container">
    <h2>FA 선수 연봉 투표</h2>
    
    <div class="player-card-list">
    <?php foreach ($fa_players as $id => $player): ?>
        <a href="fa_vote.php?player_id=<?php echo $id; ?>" 
           class="player-card <?php echo ($id == $current_player_id) ? 'active' : ''; ?>">
           <img src="<?php echo $player['img_path']; ?>" alt="<?php echo $player['name']; ?> 이미지">
        </a>
    <?php endforeach; ?>
</div>
    
    <div class="vote-section">
        
        <div class="player-info-box">
            <h3 style="color: #64ffda; border-bottom: 1px solid #444444; padding-bottom: 5px;">선수 기본 정보</h3>
            <div class="player-info-detail">
                <strong>선수명</strong>
                <span><?php echo htmlspecialchars($current_player['name']); ?> 선수</span>
                <strong>소속팀</strong>
                <span><?php echo htmlspecialchars($current_player['team_name']); ?></span>
                <strong>포지션</strong>
                <span><?php echo htmlspecialchars($current_player['pos_name'] ?? '정보 없음'); ?></span>
                <strong>생년월일</strong>
                <span><?php echo htmlspecialchars($current_player['birth_date']); ?></span>
                <strong>경력</strong>
                <span><?php echo htmlspecialchars($current_player['career']); ?></span>
                <strong>직전 연봉</strong>
                <span><?php echo number_format($current_player['base_salary'] ?? 0); ?> 만원</span>
            </div>
        </div>
        
        <div class="vote-input-box">
            <form method="POST" action="fa_vote.php" id="voteForm">
                <input type="hidden" name="player_id" value="<?php echo $current_player_id; ?>">
                
                <h3 style="color: #64ffda; margin-top: 0;">내가 생각하는 선수 연봉 입력 (만원)</h3>
                
                <input type="number" 
                       name="vote_amount" 
                       id="vote_amount" 
                       placeholder="예: 70000" 
                       required 
                       min="1" 
                       value="<?php echo $user_vote_amount > 0 ? number_format($user_vote_amount, 0, '', '') : ''; ?>"> 

                <div class="vote-amount-display" id="display_salary">
                     <?php echo $user_vote_amount > 0 ? number_format($user_vote_amount) : '000'; ?>
                </div>

                <div class="vote-actions">
                    <button type="submit" class="btn-submit" 
                            style="flex-grow: 2;"><?php echo $action_button_text; ?></button>
                            
                    <button type="button" class="btn-reset" onclick="document.getElementById('vote_amount').value=''; document.getElementById('display_salary').innerHTML='000';">입력 초기화</button>
                    
                    <?php if ($has_existing_vote): ?>
                        <button type="button" class="btn-delete" onclick="document.getElementById('deleteForm').submit()">투표 삭제 (DELETE)</button>
                    <?php endif; ?>
                </div>
            </form>
            
            <form id="deleteForm" method="POST" action="fa_vote.php" style="display:none;">
                <input type="hidden" name="player_id" value="<?php echo $current_player_id; ?>">
                <input type="hidden" name="action" value="delete_vote">
            </form>
            
            <?php if (!empty($message)): ?>
                <div class="vote-message">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
        </div>
    </div> <div class="average-result-box">
        <h3>전체 투표 결과 (총 <?php echo number_format($total_votes); ?>명 참여)</h3>
        <p>
            현재까지 **<?php echo htmlspecialchars($current_player['name']); ?>** 선수에 대해 투표된 <br>
            대중의 평균 연봉 기대치는
        </p>
        <span class="average-salary-text">
            <?php echo number_format($average_salary); ?>
        </span>
        <p>만원 입니다.</p>
    </div>
    
</div>

<script>
    // 입력된 연봉 금액을 실시간으로 표시
    document.getElementById('vote_amount').addEventListener('input', function() {
        let value = this.value;
        let display = document.getElementById('display_salary');
        if (value.trim() === '') {
            display.innerHTML = '000';
        } else {
            // 천 단위 콤마 찍어서 표시
            // 입력 필드는 콤마 없이 숫자로만 처리, 출력 시 콤마 추가
            display.innerHTML = Number(value).toLocaleString('ko-KR');
        }
    });
    
    // 페이지 로드 시 기존 값 (user_vote_amount)이 있으면 display_salary 업데이트
    window.addEventListener('load', function() {
        const voteInput = document.getElementById('vote_amount');
        const display = document.getElementById('display_salary');
        if (voteInput && display && voteInput.value.trim() !== '') {
            display.innerHTML = Number(voteInput.value).toLocaleString('ko-KR');
        }
    });
</script>
</body>
</html>