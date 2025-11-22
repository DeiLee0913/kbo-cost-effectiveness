<?php
// db_test.php: DB 연결 테스트 및 player 테이블 데이터 조회

// 1. DB 연결 설정 파일을 불러옵니다.
// 이 파일은 $conn 변수를 생성하고 연결을 담당합니다.
include 'db_connect.php'; 

$player_list = [];
$error_message = '';

// 2. DB 연결 성공 시에만 데이터 조회 쿼리 실행
if ($conn) {
    // player 테이블에서 상위 20개 레코드와 관련 정보 조회
    $sql = "
        SELECT 
            P.player_id, 
            P.name, 
            T.team_name, 
            PD.pos_name,
            P.status 
        FROM player P
        LEFT JOIN team T ON P.team_id = T.team_id
        LEFT JOIN position_detail PD ON P.pos_id = PD.pos_id
        LIMIT 20
    ";
    
    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $player_list[] = $row;
        }
    } else {
        // 쿼리 실행 실패 시 오류 메시지 저장
        $error_message = "쿼리 실행 실패: " . $conn->error;
    }

    // DB 연결 닫기
    $conn->close();
} else {
    // db_connect.php에서 연결 실패 시, 이미 die()로 중단되었을 가능성이 높지만
    // 만약을 대비해 오류 메시지 설정
    $error_message = "DB 연결 실패. db_connect.php 설정을 확인하세요.";
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DB 연결 테스트 결과</title>
    <style>
        body { font-family: 'Inter', sans-serif; margin: 20px; background-color: #f4f7f6; color: #333; }
        .container { max-width: 800px; margin: auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); }
        h2 { color: #1c7ed6; border-bottom: 2px solid #e9ecef; padding-bottom: 10px; margin-bottom: 20px; }
        .error { color: #e03131; font-weight: bold; padding: 15px; border: 1px solid #e03131; background-color: #fff3f3; border-radius: 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.9em; }
        th, td { border: 1px solid #dee2e6; padding: 12px; text-align: left; }
        th { background-color: #1c7ed6; color: white; }
        tr:nth-child(even) { background-color: #f8f9fa; }
    </style>
</head>
<body>
<div class="container">
    <h2>DB 연결 및 데이터 조회 테스트</h2>

    <?php if ($error_message): ?>
        <div class="error">
            <strong>테스트 실패:</strong> <?php echo $error_message; ?>
        </div>
    <?php elseif (empty($player_list)): ?>
        <div class="error">
            <strong>데이터 없음:</strong> DB 연결은 성공했으나, `player` 테이블에 데이터가 조회되지 않습니다. `dbinsert.sql` 실행 여부를 확인하세요.
        </div>
    <?php else: ?>
        <p style="color: #2f9e44; font-weight: bold;">✅ DB 연결 성공! `player` 테이블에서 20개 레코드를 성공적으로 조회했습니다.</p>
        
        <h3>조회된 선수 목록 (상위 20개)</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>이름</th>
                    <th>팀</th>
                    <th>포지션</th>
                    <th>상태</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($player_list as $player): ?>
                <tr>
                    <td><?php echo htmlspecialchars($player['player_id']); ?></td>
                    <td><?php echo htmlspecialchars($player['name']); ?></td>
                    <td><?php echo htmlspecialchars($player['team_name']); ?></td>
                    <td><?php echo htmlspecialchars($player['pos_name']); ?></td>
                    <td><?php echo htmlspecialchars($player['status']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>