<?php include 'db_connect.php'; ?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>수비 기록 조회</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <style>
        body { font-family: 'Pretendard', sans-serif; background-color: #f8f9fa; padding: 20px; }
        .nav-bar { background-color: #333; padding: 15px 0; margin-bottom: 30px; }
        .nav-link { text-decoration: none; color: #ccc; font-weight: bold; padding: 8px 12px; margin-right: 10px; }
        .nav-link:hover, .nav-link.active { color: #fff; background-color: #00d2d3; border-radius: 5px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; }
        h2 { border-left: 5px solid #00d2d3; padding-left: 15px; margin-bottom: 20px; color: #333; }
    </style>
</head>
<body>

<nav class="nav-bar">
    <div style="max-width: 1100px; margin: auto; display: flex; justify-content: space-between;">
        <a href="/team17/cost_efficiency_rank.php" class="nav-link">가성비 랭킹</a>
        <a href="/team17/player_detail.php" class="nav-link">선수 상세 기록</a>
        <a href="/team17/fa_vote.php" class="nav-link">FA 연봉 예측</a>
        <a href="/team17/analysis_window.php" class="nav-link">선수 성장 추이</a>
        <a href="/team17/analysis_aggregate.php" class="nav-link">팀/포지션별 평균</a>
        <a href="/team17/analysis_rollup.php" class="nav-link">계층별 효율 분석</a>
        <a href="/team17/attack_stat.php" class="nav-link">타격 기록 조회</a>
        <a href="/team17/defense_stat.php" class="nav-link active">수비 기록 조회</a>
    </div>
</nav>

<div class="container">
    <h2>🛡️ 수비 기록 (Fielding Stats)</h2>
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
                <th>dWAR</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // 수비 데이터 가져오기 (defense_stat 테이블 조인)
            $sql = "
                SELECT 
                    p.name, 
                    t.team_name, 
                    d.* FROM defense_stat d
                JOIN player p ON d.player_id = p.player_id
                LEFT JOIN team t ON p.team_id = t.team_id
                ORDER BY d.G DESC
            ";
            
            if(isset($conn)) {
                $result = $conn->query($sql);
                $rank = 1;
                if ($result) {
                    while($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . $rank++ . "</td>";
                        echo "<td>" . htmlspecialchars($row["name"]) . "</td>";
                        echo "<td>" . htmlspecialchars($row["team_name"]) . "</td>";
                        // DB 컬럼명 그대로 매칭
                        echo "<td>" . $row["G"] . "</td>";
                        echo "<td>" . $row["GS"] . "</td>";
                        echo "<td>" . $row["ASS"] . "</td>";
                        echo "<td>" . $row["E"] . "</td>";
                        echo "<td>" . $row["RF9"] . "</td>";
                        echo "<td>" . $row["dWAR"] . "</td>";
                        echo "</tr>";
                    }
                }
            }
            ?>
        </tbody>
    </table>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
    $(document).ready(function() {
        $('#defenseTable').DataTable({
            "order": [[ 3, "desc" ]], // 경기수(G) 기준 내림차순
            "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/ko.json" }
        });
    });
</script>
</body>
</html>