<?php
include 'db_connect.php';

$seasons = [];
$positions = [];

$res = $conn->query("SELECT season_id, year FROM season ORDER BY year DESC");
while($r = $res->fetch_assoc()) { $seasons[$r['season_id']] = $r['year']; }

$res = $conn->query("SELECT DISTINCT pos_category FROM position_detail WHERE pos_category IS NOT NULL AND pos_category != '투수'");
while($r = $res->fetch_assoc()) { $positions[] = $r['pos_category']; }

$search_season = isset($_GET['season']) ? $_GET['season'] : 11;
$search_pos = isset($_GET['pos']) ? $_GET['pos'] : '내야수';
$current_sort = isset($_GET['sort']) ? $_GET['sort'] : 'avg_salary';

$sort_whitelist = [
    'avg_salary', 'avg_bat', 'avg_hit', 'avg_rbi', 'avg_owar', 'avg_dwar'
];

if (!in_array($current_sort, $sort_whitelist)) {
    $current_sort = 'avg_salary';
}

$sql = "
    SELECT 
        t.team_name,
        pd.pos_category,
        COUNT(p.player_id) as player_count,
        AVG(s.amount) as avg_salary,
        AVG(a.AVG) as avg_bat,
        AVG(a.H) as avg_hit,
        AVG(a.RBI) as avg_rbi,
        AVG(a.OWAR) as avg_owar,
        AVG(a.dWAR) as avg_dwar
    FROM player p
    JOIN team t ON p.team_id = t.team_id
    JOIN salary s ON p.player_id = s.player_id
    JOIN attack_stat a ON p.player_id = a.player_id AND s.season_id = a.season_id
    JOIN position_detail pd ON p.pos_id = pd.pos_id
    WHERE s.season_id = ? 
      AND pd.pos_category = ?
    GROUP BY t.team_name 
    ORDER BY $current_sort DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $search_season, $search_pos);
$stmt->execute();
$result = $stmt->get_result();

$stats_list = [];
while ($row = $result->fetch_assoc()) {
    $stats_list[] = $row;
}

function sortLink($label, $col, $current_sort, $search_season, $search_pos) {
    $next_sort = ($current_sort === $col) ? 'avg_salary' : $col;
    $arrow = ($current_sort === $col) ? ' ▼' : '';
    
    return "<a href='?season=$search_season&pos=$search_pos&sort=$next_sort' style='color:#FFF; text-decoration:none;'>$label$arrow</a>";
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>팀/포지션별 평균 비교</title>
    <style>
        body { font-family: 'Pretendard', sans-serif; margin: 0; background-color: #121212; color: #E0E0E0; }
        .nav-bar { background-color: #212121; padding: 15px 0; border-bottom: 1px solid #333; }
        .nav-link { color: #CCC; text-decoration: none; padding: 0 15px; font-weight: bold; }
        .nav-link.active { color: #64ffda; border-bottom: 3px solid #64ffda; }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .filter-box { background: #212121; padding: 25px; border-radius: 12px; border: 1px solid #444; margin-bottom: 30px; display: flex; align-items: center; gap: 15px; }
        .filter-title { font-size: 18px; font-weight: bold; color: #FFF; margin-right: auto; }
        select, button { padding: 10px 15px; border-radius: 6px; border: 1px solid #555; background: #333; color: #FFF; }
        button { background: #64ffda; color: #121212; font-weight: bold; border: none; cursor: pointer; }
        button:hover { background: #92ffe6; }
        .result-table { width: 100%; border-collapse: collapse; background: #212121; border-radius: 8px; overflow: hidden; }
        .result-table th { background: #333; color: #FFF; padding: 15px; text-align: center; border-bottom: 1px solid #555; font-size: 15px; cursor: pointer; }
        .result-table th:hover { background: #444; }
        .result-table td { padding: 15px; text-align: center; border-bottom: 1px solid #444; color: #DDD; font-size: 15px; }
        .result-table tr:hover td { background: #2c2c2c; }
        .team-badge { font-weight: bold; font-size: 16px; color: #FFF; }
        .salary-text { color: #64ffda; font-weight: bold; }
        .war-text { color: #ff6b6b; font-weight: bold; }
        .dwar-text { color: #4dabf7; font-weight: bold; }
        .empty-msg { text-align: center; padding: 50px; color: #777; }
    </style>
</head>
<body>

<nav class="nav-bar">
    <div style="max-width: 1100px; margin: auto; display: flex; justify-content: space-between;">
        <a href="/team17/cost_efficiency_rank.php" class="nav-link">가성비 랭킹</a>
        <a href="/team17/player_detail.php" class="nav-link">선수별 페이지</a>
        <a href="/team17/fa_vote.php" class="nav-link">선수 연봉 투표</a>
        <a href="/team17/player_growth.php" class="nav-link">선수 성장 추이</a>
        <a href="/team17/analysis_aggregate.php" class="nav-link active">팀/포지션별 연봉</a>
        <a href="/team17/analysis_rollup.php" class="nav-link">연봉 계층별 효율</a>
        <a href="/team17/attack_stat.php" class="nav-link">타격 기록</a>
        <a href="/team17/defense_stat.php" class="nav-link">수비 기록</a>
    </div>
</nav>

<div class="container">
    <h2 style="color:#FFF; border-bottom:2px solid #333; padding-bottom:15px; margin-bottom:20px;">팀 포지션 별 연봉 및 성적 비교</h2>

    <form method="GET" class="filter-box">
        <div class="filter-title">조건 설정</div>
        <select name="season">
            <?php foreach($seasons as $id => $y) echo "<option value='$id' ".($search_season==$id?'selected':'').">$y 시즌</option>"; ?>
        </select>
        <select name="pos">
            <?php foreach($positions as $p) echo "<option value='$p' ".($search_pos==$p?'selected':'').">$p</option>"; ?>
        </select>
        <button type="submit">조회하기</button>
    </form>

    <?php if(empty($stats_list)): ?>
        <div class="empty-msg">해당 조건의 데이터가 없습니다.</div>
    <?php else: ?>
        <table class="result-table">
            <thead>
                <tr>
                    <th>팀명</th>
                    <th>포지션</th>
                    <th>인원</th>
                    <th><?php echo sortLink('평균 연봉', 'avg_salary', $current_sort, $search_season, $search_pos); ?></th>
                    <th><?php echo sortLink('평균 타율', 'avg_bat', $current_sort, $search_season, $search_pos); ?></th>
                    <th><?php echo sortLink('평균 안타', 'avg_hit', $current_sort, $search_season, $search_pos); ?></th>
                    <th><?php echo sortLink('평균 타점', 'avg_rbi', $current_sort, $search_season, $search_pos); ?></th>
                    <th><?php echo sortLink('평균 공격 WAR', 'avg_owar', $current_sort, $search_season, $search_pos); ?></th>
                    <th><?php echo sortLink('평균 수비 WAR', 'avg_dwar', $current_sort, $search_season, $search_pos); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($stats_list as $row): ?>
                <tr>
                    <td class="team-badge"><?php echo $row['team_name']; ?></td>
                    <td><?php echo $row['pos_category']; ?></td>
                    <td><?php echo $row['player_count']; ?>명</td>
                    <td class="salary-text"><?php echo number_format($row['avg_salary']); ?>만원</td>
                    <td><?php echo number_format($row['avg_bat'], 3); ?></td>
                    <td><?php echo number_format($row['avg_hit'], 1); ?>개</td>
                    <td><?php echo number_format($row['avg_rbi'], 1); ?></td>
                    <td class="war-text"><?php echo number_format($row['avg_owar'], 2); ?></td>
                    <td class="dwar-text"><?php echo number_format($row['avg_dwar'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>