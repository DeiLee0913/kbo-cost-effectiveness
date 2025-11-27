<?php

// 1. DB 연결 설정
include 'db_connect.php'; 

// 2. 변수 초기화
// $current_year는 $current_season_id를 기반으로 나중에 설정
$current_season_id = 11; // 2025년 (기본값)

// 3. 시즌 목록 가져오기
$seasons_option = [];
$season_query = "SELECT season_id, year FROM season ORDER BY year DESC";
$season_result = $conn->query($season_query);

if ($season_result) {
    while ($row = $season_result->fetch_assoc()) {
        $seasons_option[$row['season_id']] = $row['year'];
    }
    
    if (!empty($seasons_option)) {
        $current_season_id = array_key_first($seasons_option);
    }
}
// 4. 사용자 입력 처리
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['season_id'])) {
    $current_season_id = intval($_POST['season_id']);
}

// 선택된 시즌 ID에 해당하는 연도 설정
$current_year = $seasons_option[$current_season_id] ?? 2025;


// 5. 데이터 조회 쿼리
$sql = "
    SELECT 
        p.name, 
        t.team_name, 
        a.AVG, a.G, a.AB, a.R, a.H, a.HR, a.RBI, a.OWAR, a.dWAR, a.ePA, a.OBP, a.SLG, a.wRC_plus
    FROM attack_stat a
    JOIN player p ON a.player_id = p.player_id
    LEFT JOIN team t ON p.team_id = t.team_id
    WHERE a.season_id = ? 
    ORDER BY a.AVG DESC, a.HR DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $current_season_id);
// 쿼리 실행 실패 시 에러 출력
if (!$stmt->execute()) {
    die("쿼리 실행 오류: " . $stmt->error);
}

$result = $stmt->get_result();

$attack_data = [];
while ($row = $result->fetch_assoc()) {
    $attack_data[] = $row;
}

$stmt->close();
// $conn->close();
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>타격 기록 조회</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; margin: 0; background-color: #121212; color: #E0E0E0; }
        .nav-bar { background-color: #212121; padding: 15px 0; border-bottom: 1px solid #333333; }
        .nav-link { color: #CCCCCC; text-decoration: none; padding: 0 15px; font-weight: bold; }
        .nav-link:hover { color: #FFFFFF; }
        .nav-link.active { color: #64ffda; border-bottom: 3px solid #64ffda; }
        .container { max-width: 1200px; margin: 30px auto; background: #212121; padding: 30px; border-radius: 8px; }
        h2 { color: #FFFFFF; border-bottom: 2px solid #333333; padding-bottom: 10px; margin-bottom: 20px; }
        
        .filter-form { background-color: #2c2c2c; padding: 20px; border-radius: 6px; margin-bottom: 20px; }
        select, button { padding: 8px; background-color: #333; color: #fff; border: 1px solid #555; margin-right: 10px;}
        button { background-color: #64ffda; color: #121212; font-weight: bold; border: none; cursor: pointer; }
        
        table.dataTable { background-color: #212121; color: #E0E0E0; width: 100%; }
        table.dataTable th { background-color: #333; color: #fff; text-align: center; }
        table.dataTable td { background-color: #212121; text-align: center; border-bottom: 1px solid #444; }
        
        .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter, .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_paginate { color: #ccc !important; }
    </style>
</head>
<body>

<nav class="nav-bar">
    <div style="max-width: 1100px; margin: auto; display: flex; justify-content: space-between;">
        <a href="/team17/cost_efficiency_rank.php" class="nav-link">가성비 랭킹</a>
        <a href="/team17/player_detail.php" class="nav-link">선수별 페이지</a>
        <a href="/team17/fa_vote.php" class="nav-link">선수 연봉 투표</a>
        <a href="/team17/analysis_window.php" class="nav-link">선수 성장 추이</a>
        <a href="/team17/analysis_aggregate.php" class="nav-link">팀/포지션별 연봉</a>
        <a href="/team17/analysis_rollup.php" class="nav-link">연봉 계층별 효율</a>
        <a href="/team17/attack_stat.php" class="nav-link active">타격 기록</a>
        <a href="/team17/defense_stat.php" class="nav-link">수비 기록</a>
    </div>
</nav>

<div class="container">
    <h2><?php echo $current_year; ?> 시즌 타격 기록 (Batting Stats)</h2>

    <form method="POST" action="attack_stat.php" class="filter-form">
        <label>시즌 선택: </label>
        <select name="season_id">
            <?php foreach ($seasons_option as $id => $year): ?>
                <option value="<?php echo $id; ?>" <?php echo ($id == $current_season_id) ? 'selected' : ''; ?>>
                    <?php echo $year; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">조회</button>
    </form>

    <table id="attackTable" class="display" style="width:100%">
        <thead>
            <tr>
                <th>순위</th>
                <th>이름</th>
                <th>팀</th>
                <th>타율(AVG)</th>
                <th>경기(G)</th>
                <th>타수(AB)</th>
                <th>득점(R)</th>
                <th>안타(H)</th>
                <th>홈런(HR)</th>
                <th>타점(RBI)</th>
                <th>oWAR</th> 
                <th>dWAR</th> 
            </tr>
        </thead>
        <tbody>
            <?php 
            $rank = 1;
            foreach ($attack_data as $row): ?>
            <tr>
                <td><?php echo $rank++; ?></td>
                <td><?php echo htmlspecialchars($row["name"]); ?></td>
                <td><?php echo htmlspecialchars($row["team_name"]); ?></td>
                <td style="color: #64ffda; font-weight:bold;"><?php echo $row["AVG"]; ?></td>
                <td><?php echo $row["G"]; ?></td>
                <td><?php echo $row["AB"]; ?></td>
                <td><?php echo $row["R"]; ?></td>
                <td><?php echo $row["H"]; ?></td>
                <td><?php echo $row["HR"]; ?></td>
                <td><?php echo $row["RBI"]; ?></td>
                <td><?php echo $row["OWAR"]; ?></td>
                <td><?php echo $row["dWAR"]; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
    $(document).ready(function() {
        $('#attackTable').DataTable({
            "order": [[ 3, "desc" ]], 
            "pageLength": 20,
            "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/ko.json" }
        });
    });
</script>

</body>
</html>