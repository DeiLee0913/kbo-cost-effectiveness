<?php
include 'db_connect.php';



$seasons = [];
$res = $conn->query("SELECT season_id, year FROM season ORDER BY year DESC");
while($r = $res->fetch_assoc()) { $seasons[$r['season_id']] = $r['year']; }

$current_season = isset($_GET['season']) ? intval($_GET['season']) : 11;

$league_pos_stats = [];
$sql_league = "
    SELECT 
        pd.pos_category,
        AVG(s.amount) as avg_salary,
        AVG(IFNULL(att.OWAR, 0)) as avg_war
    FROM player p
    JOIN salary s ON p.player_id = s.player_id
    JOIN position_detail pd ON p.pos_id = pd.pos_id
    LEFT JOIN attack_stat att ON p.player_id = att.player_id AND s.season_id = att.season_id
    WHERE s.season_id = ?
    GROUP BY pd.pos_category
";
$stmt_l = $conn->prepare($sql_league);
$stmt_l->bind_param("i", $current_season);
$stmt_l->execute();
$result_l = $stmt_l->get_result();
while($row = $result_l->fetch_assoc()) {
    $league_pos_stats[$row['pos_category']] = $row;
}

$sql = "
    SELECT * FROM (
        SELECT 
            t.team_name,
            pd.pos_category,
            CASE 
                WHEN s.amount >= 50000 THEN 'S급 (5억 이상)'
                WHEN s.amount >= 20000 THEN 'A급 (2억~5억)'
                WHEN s.amount >= 5000  THEN 'B급 (5천~2억)'
                ELSE 'C급 (5천 미만)'
            END AS salary_grade,
            COUNT(p.player_id) as player_count,
            AVG(s.amount) as avg_salary,
            AVG(IFNULL(att.OWAR, 0)) as avg_war
        FROM player p
        JOIN team t ON p.team_id = t.team_id
        JOIN salary s ON p.player_id = s.player_id
        JOIN position_detail pd ON p.pos_id = pd.pos_id
        LEFT JOIN attack_stat att ON p.player_id = att.player_id AND s.season_id = att.season_id
        WHERE s.season_id = ?
        GROUP BY t.team_name, pd.pos_category, salary_grade WITH ROLLUP
    ) AS sub
    ORDER BY 
        sub.team_name IS NULL DESC, sub.team_name ASC,
        sub.pos_category IS NULL DESC, sub.pos_category ASC,
        sub.salary_grade IS NULL DESC,
        CASE 
            WHEN sub.salary_grade LIKE 'S급%' THEN 1
            WHEN sub.salary_grade LIKE 'A급%' THEN 2
            WHEN sub.salary_grade LIKE 'B급%' THEN 3
            WHEN sub.salary_grade LIKE 'C급%' THEN 4
            ELSE 5 
        END ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $current_season);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>연봉 계층별 효율</title>
    <style>
        body { font-family: 'Pretendard', sans-serif; margin: 0; background-color: #121212; color: #E0E0E0; overflow-y: hidden; }
        .nav-bar { background-color: #212121; padding: 15px 0; border-bottom: 1px solid #333; }
        .nav-link { color: #CCC; text-decoration: none; padding: 0 15px; font-weight: bold; }
        .nav-link.active { color: #64ffda; border-bottom: 3px solid #64ffda; }
        
        .container { 
            max-width: 1200px; 
            margin: 30px auto; 
            padding: 0 20px; 
            height: calc(100vh - 150px); 
            display: flex; 
            flex-direction: column; 
        }

        .filter-box { background: #212121; padding: 20px; border-radius: 8px; border: 1px solid #444; margin-bottom: 20px; text-align: right; flex-shrink: 0; }
        select, button { padding: 8px 12px; background: #333; color: #FFF; border: 1px solid #555; border-radius: 4px; }
        button { background: #64ffda; color: #121212; font-weight: bold; border: none; cursor: pointer; }

        .table-scroll-area {
            flex-grow: 1;
            overflow-y: auto;
            border-radius: 8px;
            border: 1px solid #444;
            background: #212121;
        }

        .tree-table { width: 100%; border-collapse: collapse; font-size: 15px; }
        .tree-table th { 
            background: #333; 
            color: #FFF; 
            padding: 15px; 
            text-align: center; 
            border-bottom: 1px solid #555; 
            position: sticky; 
            top: 0; 
            z-index: 100;
            height: 50px; 
            box-sizing: border-box;
        }
        
        .tree-table td { padding: 12px; border-bottom: 1px solid #444; vertical-align: middle; }

        .row-grand-total td {
            position: sticky;
            top: 50px; 
            z-index: 90;
            background-color: #444 !important;
            color: #fcc419; 
            font-weight: 900; 
            border-bottom: 2px solid #64ffda;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        .row-grand-total td:first-child { text-align: center; font-size: 1.1em; }

        .row-team-total { background-color: #2a2a2a; color: #64ffda; font-weight: bold; border-top: 2px solid #555; }
        .row-team-total td { padding-left: 20px; font-size: 1.05em; }

        .row-pos-total { background-color: #1f1f1f; color: #EEE; font-weight: bold; }
        .row-pos-total .title-col { padding-left: 40px; border-left: 3px solid #444; }

        .row-detail { background-color: #121212; color: #AAA; }
        .row-detail .title-col { padding-left: 70px; font-size: 0.95em; border-left: 1px solid #333; }

        .league-avg { font-size: 0.85em; color: #888; margin-left: 8px; font-weight: normal; }
        .comp-up { color: #ff6b6b; font-size: 0.85em; margin-left: 5px; }
        .comp-down { color: #4dabf7; font-size: 0.85em; margin-left: 5px; }
    </style>
</head>
<body>

<nav class="nav-bar">
    <div style="max-width: 1100px; margin: auto; display: flex; justify-content: space-between;">
        <a href="/team17/cost_efficiency_rank.php" class="nav-link">가성비 랭킹</a>
        <a href="/team17/player_detail.php" class="nav-link">선수별 페이지</a>
        <a href="/team17/fa_vote.php" class="nav-link">선수 연봉 투표</a>
        <a href="/team17/player_growth.php" class="nav-link">선수 성장 추이</a>
        <a href="/team17/analysis_aggregate.php" class="nav-link">팀/포지션별 연봉</a>
        <a href="/team17/analysis_rollup.php" class="nav-link active">연봉 계층별 효율</a>
        <a href="/team17/attack_stat.php" class="nav-link">타격 기록</a>
        <a href="/team17/defense_stat.php" class="nav-link">수비 기록</a>
    </div>
</nav>

<div class="container">
    <h2 style="color:#FFF; border-bottom:2px solid #333; padding-bottom:15px; flex-shrink:0;">연봉 규모별 효율 분석 (Rollup)</h2>

    <form method="GET" class="filter-box">
        <label style="color:#CCC; margin-right:10px;">분석 시즌:</label>
        <select name="season">
            <?php foreach($seasons as $id => $y) echo "<option value='$id' ".($current_season==$id?'selected':'').">$y 시즌</option>"; ?>
        </select>
        <button type="submit">분석 실행</button>
    </form>

    <div class="table-scroll-area">
        <table class="tree-table">
            <thead>
                <tr>
                    <th width="40%">구분 (계층 구조)</th>
                    <th width="20%">선수 수</th>
                    <th width="20%">평균 연봉</th>
                    <th width="20%">평균 WAR</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                foreach ($data as $row): 
                    $team = $row['team_name'];
                    $pos = $row['pos_category'];
                    $grade = $row['salary_grade'];
                    
                    if ($team === null) {
                        echo "<tr class='row-grand-total'><td> KBO 리그 전체 요약</td>";
                    } elseif ($pos === null) {
                        echo "<tr class='row-team-total'><td>$team 전체</td>";
                    } elseif ($grade === null) {
                        echo "<tr class='row-pos-total'><td class='title-col'>$pos</td>";
                    } else {
                        echo "<tr class='row-detail'><td class='title-col'>$grade</td>";
                    }
                ?>
                    <td style="text-align:center;"><?php echo number_format($row['player_count']); ?>명</td>
                    
                    <td style="text-align:center;">
                        <?php echo number_format($row['avg_salary']); ?>만원
                        <?php 
                        if ($grade === null && $pos !== null && isset($league_pos_stats[$pos])) {
                            $l_avg = $league_pos_stats[$pos]['avg_salary'];
                            echo "<div class='league-avg'>(리그평균: " . number_format($l_avg) . ")</div>";
                        }
                        ?>
                    </td>

                    <td style="text-align:center; font-weight:bold;">
                        <?php echo number_format($row['avg_war'], 2); ?>
                        <?php 
                        if ($grade === null && $pos !== null && isset($league_pos_stats[$pos])) {
                            $l_war = $league_pos_stats[$pos]['avg_war'];
                            $diff = $row['avg_war'] - $l_war;
                            $diff_text = ($diff > 0 ? "▲" : "▼") . number_format(abs($diff), 2);
                            $color_class = ($diff > 0 ? "comp-up" : "comp-down");
                            echo "<span class='$color_class'>$diff_text</span>";
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if(empty($data)): ?>
                    <tr><td colspan="4" style="text-align:center; padding:30px;">데이터가 없습니다.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>