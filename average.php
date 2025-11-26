<?php
// =================================================================
// analysis_aggregate.php: 팀/포지션별 평균 비교 (최종 수정: 모든 색상 제거)
// =================================================================

include 'db_connect.php';

// 1. 필터 옵션 가져오기
$seasons = [];
$teams = [];
$positions = [];

$res = $conn->query("SELECT season_id, year FROM season ORDER BY year DESC");
while($r = $res->fetch_assoc()) { $seasons[$r['season_id']] = $r['year']; }

$res = $conn->query("SELECT team_id, team_name FROM team");
while($r = $res->fetch_assoc()) { $teams[$r['team_id']] = $r['team_name']; }

$res = $conn->query("SELECT DISTINCT pos_category FROM position_detail WHERE pos_category IS NOT NULL");
while($r = $res->fetch_assoc()) { $positions[] = $r['pos_category']; }

// 2. 사용자 입력 변수
$search_season = isset($_GET['season']) ? $_GET['season'] : 11;
$search_team = isset($_GET['team']) ? $_GET['team'] : 'ALL';
$search_pos = isset($_GET['pos']) ? $_GET['pos'] : '투수';

// 3. 데이터 조회 쿼리
$sql = "
    SELECT 
        t.team_name,
        pd.pos_category,
        COUNT(p.player_id) as player_count,
        AVG(s.amount) as avg_salary,
        AVG(a.AVG) as avg_bat,
        AVG(a.HR) as avg_hr,
        AVG(a.RBI) as avg_rbi,
        AVG(a.OBP) as avg_obp,
        AVG(a.SLG) as avg_slg,
        AVG(a.OWAR) as avg_owar,
        AVG(d.dWAR) as avg_dwar
    FROM player p
    JOIN team t ON p.team_id = t.team_id
    JOIN salary s ON p.player_id = s.player_id
    LEFT JOIN attack_stat a ON p.player_id = a.player_id AND s.season_id = a.season_id
    LEFT JOIN defense_stat d ON p.player_id = d.player_id AND s.season_id = d.season_id
    JOIN position_detail pd ON p.pos_id = pd.pos_id
    WHERE s.season_id = ? 
      AND pd.pos_category = ?
";

if ($search_team !== 'ALL') {
    $sql .= " AND p.team_id = " . intval($search_team);
}

$sql .= " GROUP BY t.team_name ORDER BY avg_salary DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $search_season, $search_pos);
$stmt->execute();
$result = $stmt->get_result();

$stats_list = [];
while ($row = $result->fetch_assoc()) {
    $stats_list[] = $row;
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>팀/포지션별 평균 비교</title>
    <style>
        /* 공통 디자인 */
        body { font-family: 'Pretendard', sans-serif; margin: 0; background-color: #121212; color: #E0E0E0; }
        .nav-bar { background-color: #212121; padding: 15px 0; border-bottom: 1px solid #333; }
        .nav-link { color: #CCC; text-decoration: none; padding: 0 15px; font-weight: bold; }
        .nav-link.active { color: #64ffda; border-bottom: 3px solid #64ffda; }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }

        /* 필터 박스 */
        .filter-box { background: #212121; padding: 25px; border-radius: 12px; border: 1px solid #444; margin-bottom: 30px; display: flex; align-items: center; gap: 15px; }
        .filter-title { font-size: 18px; font-weight: bold; color: #FFF; margin-right: auto; }
        
        select, button { padding: 10px 15px; border-radius: 6px; border: 1px solid #555; background: #333; color: #FFF; }
        button { background: #64ffda; color: #121212; font-weight: bold; border: none; cursor: pointer; }
        button:hover { background: #92ffe6; }

        /* 결과 테이블 */
        .result-table { width: 100%; border-collapse: collapse; background: #212121; border-radius: 8px; overflow: hidden; }
        .result-table th { background: #333; color: #FFF; padding: 15px; text-align: center; border-bottom: 1px solid #555; font-size: 15px; }
        .result-table td { padding: 15px; text-align: center; border-bottom: 1px solid #444; color: #DDD; font-size: 15px; }
        .result-table tr:hover td { background: #2c2c2c; }
        
        /* 강조 스타일 */
        .team-badge { font-weight: bold; font-size: 16px; color: #FFF; }
        .salary-text { color: #64ffda; font-weight: bold; }
        .empty-msg { text-align: center; padding: 50px; color: #777; }
    </style>
</head>
<body>

<nav class="nav-bar">
    <div style="max-width: 1100px; margin: auto; display: flex; justify-content: space-between;">
        <a href="/team17/cost_efficiency_rank.php" class="nav-link">가성비 랭킹</a>
        <a href="/team17/player_detail.php" class="nav-link">선수별 페이지</a>
        <a href="/team17/fa_vote.php" class="nav-link">선수 연봉 투표</a>
        <a href="/team17/analysis_window.php" class="nav-link">선수 성장 추이</a>
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

        <select name="team">
            <option value="ALL" <?php echo ($search_team=='ALL'?'selected':''); ?>>전체 구단</option>
            <?php foreach($teams as $id => $name) echo "<option value='$id' ".($search_team==$id?'selected':'').">$name</option>"; ?>
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
                    <th>평균 연봉</th>
                    <th>평균 타율</th>
                    <th>평균 홈런</th>
                    <th>평균 타점</th>
                    <th>평균 OPS(출+장)</th>
                    <th>평균 공격 WAR</th>
                    <th>평균 수비 WAR</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($stats_list as $row): 
                    $avg_ops = $row['avg_obp'] + $row['avg_slg']; 
                ?>
                <tr>
                    <td class="team-badge"><?php echo $row['team_name']; ?></td>
                    <td><?php echo $row['pos_category']; ?></td>
                    <td><?php echo $row['player_count']; ?>명</td>
                    <td class="salary-text"><?php echo number_format($row['avg_salary']); ?>만원</td>
                    
                    <?php if($row['avg_bat'] === null && $row['avg_hr'] === null): ?>
                        <td colspan="4" style="color:#777;">- (투수) -</td>
                    <?php else: ?>
                        <td><?php echo number_format($row['avg_bat'], 3); ?></td>
                        <td><?php echo number_format($row['avg_hr'], 1); ?></td>
                        <td><?php echo number_format($row['avg_rbi'], 1); ?></td>
                        <td><?php echo number_format($avg_ops, 3); ?></td>
                    <?php endif; ?>

                    <td><?php echo number_format($row['avg_owar'], 2); ?></td>
                    
                    <td><?php echo number_format($row['avg_dwar'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="text-align:right; color:#777; margin-top:10px; font-size:13px;">* 평균 연봉 순으로 정렬됨</p>
    <?php endif; ?>

</div>

</body>
</html>