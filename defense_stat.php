<?php

// 1. DB 연결 설정
include 'db_connect.php'; 

// 2. 변수 초기화
$current_season_id = 11; // 2025년 (기본값)

// 3. 시즌 목록 가져오기 및 현재 시즌 ID 결정
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

// 4. 사용자 입력 처리 (POST 요청이 있으면 덮어쓰기)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['season_id'])) {
    $selected_id = intval($_POST['season_id']);
    if (array_key_exists($selected_id, $seasons_option)) {
        $current_season_id = $selected_id;
    }
}

// 선택된 시즌 ID에 해당하는 연도 설정
$current_year = $seasons_option[$current_season_id] ?? 2025;


// 5. 수비 데이터 조회 쿼리
$sql = "
    SELECT 
        p.name, 
        t.team_name, 
        d.G, d.GS, d.ASS, d.E, d.RF9, d.RAA, d.POSAdj, d.Err_RAA, d.WAAwoPOS
    FROM defense_stat d
    JOIN player p ON d.player_id = p.player_id
    LEFT JOIN team t ON p.team_id = t.team_id
    WHERE d.season_id = ? 
    ORDER BY d.RAA DESC, d.GS DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $current_season_id);

if (!$stmt->execute()) {
    die("쿼리 실행 오류: " . $stmt->error . " | Season ID: " . $current_season_id);
}
$result = $stmt->get_result();

$defense_data = [];
while ($row = $result->fetch_assoc()) {
    $defense_data[] = $row;
}

$stmt->close();
// $conn->close(); 
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>수비 기록 조회</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; margin: 0; background-color: #121212; color: #E0E0E0; }
        
        .nav-bar { background-color: #212121; padding: 15px 0; border-bottom: 1px solid #333333; }
        .nav-link { color: #CCCCCC; text-decoration: none; padding: 0 15px; font-weight: bold; }
        .nav-link:hover { color: #FFFFFF; }
        .nav-link.active { color: #64ffda; border-bottom: 3px solid #64ffda; }
        
        .container { max-width: 1200px; margin: 30px auto; background: #212121; padding: 30px; border-radius: 8px; box-shadow: 0 0 15px rgba(0, 0, 0, 0.5); }
        h2 { color: #FFFFFF; border-bottom: 2px solid #333333; padding-bottom: 10px; margin-bottom: 20px; }
        
        .filter-form { background-color: #2c2c2c; padding: 20px; border-radius: 6px; margin-bottom: 20px; }
        select, button { padding: 8px; background-color: #333; color: #fff; border: 1px solid #555; margin-right: 10px; border-radius: 4px;}
        button { background-color: #64ffda; color: #121212; font-weight: bold; border: none; cursor: pointer; }
        button:hover { background-color: #92ffe6; }

        table.dataTable { background-color: #212121; color: #E0E0E0; width: 100%; border-collapse: collapse; }
        table.dataTable th { background-color: #333; color: #FFFFFF; border-bottom: 1px solid #444; text-align: center; padding: 12px; }
        table.dataTable td { background-color: #212121; border-bottom: 1px solid #444; text-align: center; padding: 10px; }
        table.dataTable tr:nth-child(even) td { background-color: #2c2c2c; }
        table.dataTable tr:hover td { background-color: #444 !important; }
        
        .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter, 
        .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_paginate { color: #ccc !important; }
        .dataTables_wrapper .dataTables_length select, .dataTables_wrapper .dataTables_filter input { background-color: #333; color: #fff; border: 1px solid #555; }
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
        <a href="/team17/attack_stat.php" class="nav-link">타격 기록</a>
        <a href="/team17/defense_stat.php" class="nav-link active">수비 기록</a>
    </div>
</nav>

<div class="container">
    <h2><?php echo $current_year; ?> 시즌 수비 기록 (Fielding Stats)</h2>

    <form method="POST" action="defense_stat.php" class="filter-form">
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

<table id="defenseTable" class="display" style="width:100%">
        <thead>
            <tr>
                <th>순위</th>
                <th>이름</th>
                <th>팀</th>
                <th>경기(G)</th>
                <th>선발(GS)</th>
                <th>보살(ASS)</th>
                <th>실책(E)</th>
                <th>RF9</th>
                <th>RAA</th>       <th>POSAdj</th>    <th>Err_RAA</th> </tr>
        </thead>
        <tbody>
            <?php 
            $rank = 1;
            foreach ($defense_data as $row): ?>
            <tr>
                <td><?php echo $rank++; ?></td>
                <td><?php echo htmlspecialchars($row["name"]); ?></td>
                <td><?php echo htmlspecialchars($row["team_name"]); ?></td>
                <td><?php echo $row["G"]; ?></td>
                <td><?php echo $row["GS"]; ?></td>
                <td><?php echo $row["ASS"]; ?></td>
                <td><?php echo $row["E"]; ?></td>
                <td><?php echo $row["RF9"]; ?></td>
                <td style="color: #64ffda; font-weight:bold;"><?php echo $row["RAA"]; ?></td> <td><?php echo $row["POSAdj"]; ?></td>    <td><?php echo $row["Err_RAA"]; ?></td>  </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>


<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
    $(document).ready(function() {
        $('#defenseTable').DataTable({
            "order": [[ 8, "desc" ]],
            "pageLength": 20,
            "language": {
                "search": "선수 검색:",
                "lengthMenu": "_MENU_ 명씩 보기",
                "info": "총 _TOTAL_명 중 _START_ - _END_",
                "paginate": { "next": "다음", "previous": "이전" }
            }
        });
    });
</script>

</body>
</html>