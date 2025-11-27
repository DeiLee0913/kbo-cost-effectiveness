<?php
include 'db_connect.php';

$teams = [];
$t_res = $conn->query("SELECT team_id, team_name FROM team");
while ($row = $t_res->fetch_assoc()) {
    $teams[$row['team_id']] = $row['team_name'];
}

$positions = [];
$p_res = $conn->query("SELECT DISTINCT pos_category FROM position_detail WHERE pos_category IS NOT NULL");
while ($row = $p_res->fetch_assoc()) {
    $positions[] = $row['pos_category'];
}

$filter_team = isset($_GET['f_team']) ? $_GET['f_team'] : 'ALL';
$filter_pos = isset($_GET['f_pos']) ? $_GET['f_pos'] : 'ALL';

$player_sql = "
    SELECT p.player_id, p.name, t.team_name, pd.pos_category
    FROM player p
    LEFT JOIN team t ON p.team_id = t.team_id
    LEFT JOIN position_detail pd ON p.pos_id = pd.pos_id
    WHERE 1=1
";

if ($filter_team !== 'ALL') {
    $player_sql .= " AND p.team_id = " . intval($filter_team);
}
if ($filter_pos !== 'ALL') {
    $player_sql .= " AND pd.pos_category = '" . $conn->real_escape_string($filter_pos) . "'";
}

$player_sql .= " ORDER BY p.name";

$players = [];
$player_res = $conn->query($player_sql);
while ($row = $player_res->fetch_assoc()) {
    $players[] = $row;
}

$selected_player_id = isset($_GET['player_id']) ? intval($_GET['player_id']) : 0;
$ma_period = isset($_GET['ma_period']) ? intval($_GET['ma_period']) : 3;

$trend_data = [];
$chart_labels = [];
$data_salary = [];
$data_salary_ma = [];
$data_war = [];
$data_war_ma = [];
$player_name = "";

if ($selected_player_id > 0) {
    $sql = "
        SELECT 
            s.year,
            sal.amount AS salary,
            COALESCE(a.OWAR, 0) AS total_war
        FROM salary sal
        JOIN season s ON sal.season_id = s.season_id
        LEFT JOIN attack_stat a ON sal.player_id = a.player_id AND sal.season_id = a.season_id
        WHERE sal.player_id = ?
        ORDER BY s.year ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $selected_player_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $raw_data = [];
    while ($row = $result->fetch_assoc()) {
        $raw_data[] = $row;
    }

    $count = count($raw_data);

    for ($i = 0; $i < $count; $i++) {
        $current = $raw_data[$i];
        
        if ($i > 0) {
            $prev = $raw_data[$i - 1];
            $salary_change = $current['salary'] - $prev['salary'];
        } else {
            $salary_change = 0;
        }

        $sum_salary = 0;
        $sum_war = 0;
        $items_in_ma = 0;

        for ($j = 0; $j < $ma_period; $j++) {
            if ($i - $j >= 0) {
                $sum_salary += $raw_data[$i - $j]['salary'];
                $sum_war += $raw_data[$i - $j]['total_war'];
                $items_in_ma++;
            }
        }
        
        $salary_ma = ($items_in_ma > 0) ? $sum_salary / $items_in_ma : 0;
        $war_ma = ($items_in_ma > 0) ? $sum_war / $items_in_ma : 0;

        $row_data = [
            'year' => $current['year'],
            'salary' => $current['salary'],
            'total_war' => $current['total_war'],
            'salary_change' => $salary_change,
            'salary_ma' => $salary_ma,
            'war_ma' => $war_ma
        ];
        
        $trend_data[] = $row_data;

        $chart_labels[] = $current['year'] . '년';
        $data_salary[] = $current['salary'];
        $data_salary_ma[] = round($salary_ma);
        $data_war[] = round($current['total_war'], 2);
        $data_war_ma[] = round($war_ma, 2);
    }

    $p_info_sql = "SELECT p.name, t.team_name FROM player p LEFT JOIN team t ON p.team_id = t.team_id WHERE p.player_id = ?";
    $stmt_p = $conn->prepare($p_info_sql);
    $stmt_p->bind_param("i", $selected_player_id);
    $stmt_p->execute();
    $res_p = $stmt_p->get_result();
    if ($p_row = $res_p->fetch_assoc()) {
        $player_name = $p_row['name'] . " (" . $p_row['team_name'] . ")";
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>선수 성장 추이 분석</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Pretendard', sans-serif; margin: 0; background-color: #121212; color: #E0E0E0; }
        .nav-bar { background-color: #212121; padding: 15px 0; border-bottom: 1px solid #333; }
        .nav-link { color: #CCC; text-decoration: none; padding: 0 15px; font-weight: bold; }
        .nav-link.active { color: #64ffda; border-bottom: 3px solid #64ffda; }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        
        .filter-box { background: #212121; padding: 25px; border-radius: 12px; border: 1px solid #444; margin-bottom: 30px; display: flex; align-items: center; justify-content: center; gap: 10px; flex-wrap: wrap; }
        
        select, button { padding: 12px 15px; border-radius: 6px; border: 1px solid #555; background: #333; color: #FFF; font-size: 15px; }
        select:focus { outline: 2px solid #64ffda; }
        button { background: #64ffda; color: #121212; font-weight: bold; border: none; cursor: pointer; padding: 12px 25px; }
        button:hover { background: #92ffe6; }
        
        .label-text { font-weight: bold; color: #CCC; margin-right: 5px; }

        .chart-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .chart-card { background: #212121; padding: 20px; border-radius: 12px; border: 1px solid #444; }
        .chart-title { text-align: center; font-size: 18px; font-weight: bold; margin-bottom: 15px; color: #FFF; }

        .data-table { width: 100%; border-collapse: collapse; background: #212121; border-radius: 8px; overflow: hidden; }
        .data-table th { background: #333; padding: 12px; text-align: center; border-bottom: 1px solid #555; color: #FFF; }
        .data-table td { padding: 12px; text-align: center; border-bottom: 1px solid #444; color: #CCC; }
        
        .up { color: #ff6b6b; }   
        .down { color: #4dabf7; } 
        .ma-text { color: #fcc419; font-weight: bold; } 
        .empty-msg { text-align:center; padding: 80px; color: #777; font-size: 1.2em; }
    </style>
</head>
<body>

<nav class="nav-bar">
    <div style="max-width: 1100px; margin: auto; display: flex; justify-content: space-between;">
        <a href="/team17/cost_efficiency_rank.php" class="nav-link">가성비 랭킹</a>
        <a href="/team17/player_detail.php" class="nav-link">선수별 페이지</a>
        <a href="/team17/fa_vote.php" class="nav-link">선수 연봉 투표</a>
        <a href="/team17/player_growth.php" class="nav-link active">선수 성장 추이</a>
        <a href="/team17/analysis_aggregate.php" class="nav-link">팀/포지션별 연봉</a>
        <a href="/team17/analysis_rollup.php" class="nav-link">연봉 계층별 효율</a>
        <a href="/team17/attack_stat.php" class="nav-link">타격 기록</a>
        <a href="/team17/defense_stat.php" class="nav-link">수비 기록</a>
    </div>
</nav>

<div class="container">
    <h2 style="color:#FFF; border-bottom:2px solid #333; padding-bottom:15px; text-align:center;">선수 성장 추이 분석</h2>

    <form method="GET" class="filter-box">
        <select name="f_team" onchange="this.form.submit()">
            <option value="ALL">전체 구단</option>
            <?php foreach($teams as $id => $name): ?>
                <option value="<?php echo $id; ?>" <?php echo ($filter_team == $id) ? 'selected' : ''; ?>><?php echo $name; ?></option>
            <?php endforeach; ?>
        </select>

        <select name="f_pos" onchange="this.form.submit()">
            <option value="ALL">전체 포지션</option>
            <?php foreach($positions as $pos): ?>
                <option value="<?php echo $pos; ?>" <?php echo ($filter_pos == $pos) ? 'selected' : ''; ?>><?php echo $pos; ?></option>
            <?php endforeach; ?>
        </select>

        <select name="player_id" style="width: 250px;">
            <?php if(empty($players)): ?>
                <option value="0">조건에 맞는 선수가 없습니다</option>
            <?php else: ?>
                <option value="0" disabled <?php echo ($selected_player_id == 0) ? 'selected' : ''; ?>>선수 선택</option>
                <?php foreach($players as $p): ?>
                    <option value="<?php echo $p['player_id']; ?>" <?php echo ($selected_player_id == $p['player_id'] ? 'selected' : ''); ?>>
                        <?php echo $p['name']; ?> (<?php echo $p['team_name']; ?>)
                    </option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
        
        <select name="ma_period">
            <option value="3" <?php echo ($ma_period == 3 ? 'selected' : ''); ?>>3년 흐름</option>
            <option value="5" <?php echo ($ma_period == 5 ? 'selected' : ''); ?>>5년 흐름</option>
        </select>

        <button type="submit">분석하기</button>
    </form>

    <?php if($selected_player_id > 0 && !empty($trend_data)): ?>
    <h3 style="color: #64ffda; margin-bottom: 20px; text-align:center;">
        <?php echo $player_name; ?>의 연도별 변화 추이
    </h3>

    <div class="chart-grid">
        <div class="chart-card">
            <div class="chart-title">연봉 및 평균 흐름</div>
            <canvas id="salaryChart"></canvas>
        </div>
        <div class="chart-card">
            <div class="chart-title">성적(WAR) 및 평균 흐름</div>
            <canvas id="warChart"></canvas>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>연도</th>
                <th>연봉 (만원)</th>
                <th>전년 대비 증감</th>
                <th style="color:#fcc419;"><?php echo $ma_period; ?>년 평균 연봉 흐름</th>
                <th>총 WAR (공격)</th>
                <th style="color:#fcc419;"><?php echo $ma_period; ?>년 평균 WAR 흐름</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($trend_data as $row): ?>
            <tr>
                <td><?php echo $row['year']; ?></td>
                <td><?php echo number_format($row['salary']); ?></td>
                <td>
                    <?php 
                        $change = $row['salary_change'];
                        if ($change > 0) echo "<span class='up'>▲ " . number_format($change) . "</span>";
                        elseif ($change < 0) echo "<span class='down'>▼ " . number_format(abs($change)) . "</span>";
                        else echo "-";
                    ?>
                </td>
                <td class="ma-text"><?php echo number_format($row['salary_ma']); ?></td>
                <td><?php echo number_format($row['total_war'], 2); ?></td>
                <td class="ma-text"><?php echo number_format($row['war_ma'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <div class="empty-msg">
            선수를 선택하고 <strong>[분석하기]</strong> 버튼을 눌러주세요.
        </div>
    <?php endif; ?>

</div>

<script>
    <?php if(!empty($trend_data)): ?>
    const labels = <?php echo json_encode($chart_labels); ?>;
    
    new Chart(document.getElementById('salaryChart'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: '연봉',
                data: <?php echo json_encode($data_salary); ?>,
                borderColor: '#64ffda',
                backgroundColor: '#64ffda',
                tension: 0.1, order: 2
            }, {
                label: '<?php echo $ma_period; ?>년 평균 흐름',
                data: <?php echo json_encode($data_salary_ma); ?>,
                borderColor: '#fcc419',
                borderDash: [5, 5],
                tension: 0.4, order: 1
            }]
        },
        options: { responsive: true, plugins: { legend: { labels: { color: '#fff' } } }, scales: { x: { ticks: { color: '#ccc' } }, y: { ticks: { color: '#ccc' } } } }
    });

    new Chart(document.getElementById('warChart'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'WAR',
                data: <?php echo json_encode($data_war); ?>,
                borderColor: '#ff6b6b',
                backgroundColor: '#ff6b6b',
                tension: 0.1
            }, {
                label: '<?php echo $ma_period; ?>년 평균 흐름',
                data: <?php echo json_encode($data_war_ma); ?>,
                borderColor: '#fcc419',
                borderDash: [5, 5],
                tension: 0.4
            }]
        },
        options: { responsive: true, plugins: { legend: { labels: { color: '#fff' } } }, scales: { x: { ticks: { color: '#ccc' } }, y: { ticks: { color: '#ccc' } } } }
    });
    <?php endif; ?>
</script>

</body>
</html>