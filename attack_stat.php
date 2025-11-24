<?php 
include 'db_connect.php'; 
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>타격 기록 조회</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    
    <style>
        body { font-family: 'Pretendard', sans-serif; margin: 0; padding: 0; background-color: #f8f9fa; }
        .nav-bar { background-color: #333; padding: 15px 0; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .nav-link { text-decoration: none; color: #ccc; font-weight: bold; padding: 8px 12px; border-radius: 5px; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { color: #fff; background-color: #00d2d3; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        h2 { border-left: 5px solid #00d2d3; padding-left: 15px; margin-bottom: 20px; color: #333;}
    </style>
</head>
<body>

<nav class="nav-bar">
    <div style="max-width: 1100px; margin: auto; display: flex; justify-content: space-between; align-items: center;">
        <a href="/team17/cost_efficiency_rank.php" class="nav-link">가성비 랭킹</a>
        <a href="/team17/player_detail.php" class="nav-link">선수 상세 기록</a>
        <a href="/team17/fa_vote.php" class="nav-link">FA 연봉 예측</a>
        <a href="/team17/analysis_window.php" class="nav-link">선수 성장 추이</a>
        <a href="/team17/analysis_aggregate.php" class="nav-link">팀/포지션별 평균</a>
        <a href="/team17/analysis_rollup.php" class="nav-link">계층별 효율 분석</a>
        <a href="/team17/attack_stat.php" class="nav-link active">타격 기록 조회</a>
        <a href="/team17/defense_stat.php" class="nav-link">수비 기록 조회</a>
    </div>
</nav>

<div class="container">
    <h2>⚾ 타격 기록 (Batting Stats)</h2>

    <table id="attackTable" class="display" style="width:100%">
        <thead>
            <tr>
                <th>순위</th>
                <th>이름</th>
                <th>팀</th>
                <th>타율(AVG)</th>
                <th>경기(G)</th>
                <th>타석(ePA)</th>
                <th>타수(AB)</th>
                <th>득점(R)</th>
                <th>안타(H)</th>
                <th>홈런(HR)</th>
                <th>타점(RBI)</th>
                <th>WAR</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // [핵심] 3개의 테이블을 연결(JOIN)하는 쿼리입니다.
            // 1. attack_stat (기록) 가져와서
            // 2. player 테이블이랑 합쳐서 (이름 가져오고)
            // 3. team 테이블이랑 합쳐서 (팀 이름 가져옴)
            $sql = "
                SELECT 
                    p.name, 
                    t.team_name, 
                    a.* FROM attack_stat a
                JOIN player p ON a.player_id = p.player_id
                LEFT JOIN team t ON p.team_id = t.team_id
                ORDER BY a.AVG DESC
            ";
            
            if(isset($conn)) {
                $result = $conn->query($sql);
                
                if ($result) {
                    $rank = 1;
                    while($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . $rank++ . "</td>";
                        
                        // ★ 이제 이름과 팀명이 나옵니다!
                        echo "<td>" . htmlspecialchars($row["name"]) . "</td>"; 
                        echo "<td>" . htmlspecialchars($row["team_name"]) . "</td>"; 
                        
                        // 기록 데이터 (SQL 파일에 있는 대문자 컬럼명 그대로 사용)
                        echo "<td>" . $row["AVG"] . "</td>";
                        echo "<td>" . $row["G"] . "</td>";
                        echo "<td>" . $row["ePA"] . "</td>";
                        echo "<td>" . $row["AB"] . "</td>";
                        echo "<td>" . $row["R"] . "</td>";
                        echo "<td>" . $row["H"] . "</td>";
                        echo "<td>" . $row["HR"] . "</td>";
                        echo "<td>" . $row["RBI"] . "</td>";
                        echo "<td>" . $row["OWAR"] . "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "쿼리 오류: " . $conn->error;
                }
            } else {
                echo "<tr><td colspan='12'>DB 연결 실패</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
    $(document).ready(function() {
        $('#attackTable').DataTable({
            "order": [[ 3, "desc" ]], // 타율(4번째 컬럼) 기준 내림차순
            "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/ko.json" }
        });
    });
</script>

</body>
</html>