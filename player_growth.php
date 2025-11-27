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
    SELECT p.player_id, p.name, t.team_id, t.team_name, pd.pos_category
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

$trend_data = [];
$chart_labels = [];
$data_salary = [];
$data_rank = [];
$data_war = [];
$data_war_diff = []; 
$player_name = "";
$player_team_id = 0;

if ($selected_player_id > 0) {
    foreach ($players as $p) {
        if ($p['player_id'] == $selected_player_id) {
            $player_name = $p['name'] . " (" . $p['team_name'] . ")";
            $player_team_id = $p['team_id'];
            break;
        }
    }

    $sql = "
        SELECT 
            s.season_id,
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
        $war_diff = 0;
        
        if ($i > 0) {
            $prev = $raw_data[$i - 1];
            $prev_war = floatval($prev['total_war']);
            $curr_war = floatval($current['total_war']);
            $war_diff = $curr_war - $prev_war;
        }

        $rank_sql = "
            SELECT COUNT(*) + 1 as rank
            FROM salary s
            JOIN player p ON s.player_id = p.player_id
            WHERE s.season_id = ? 
              AND p.team_id = ? 
              AND s.amount > ?
        ";
        $stmt_r = $conn->prepare($rank_sql);
        $stmt_r->bind_param("iii", $current['season_id'], $player_team_id, $current['salary']);
        $stmt_r->execute();
        $res_r = $stmt_r->get_result();
        $rank_row = $res_r->fetch_assoc();
        $team_rank = $rank_row['rank'];

        $row_data = [
            'year' => $current['year'],
            'salary' => $current['salary'],
            'total_war' => $current['total_war'],
            'war_diff' => round($war_diff, 2),
            'team_rank' => $team_rank
        ];
        
        $trend_data[] = $row_data;

        $chart_labels[] = $current['year'] . '년';
        $data_salary[] = $current['salary'];
        $data_rank[] = $team_rank;
        $data_war[] = $current['total_war'];
        $data_war_diff[] = round($war_diff, 2);
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>선수 성장 추이 분석</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <style>
        body { font-family: 'Pretendard', sans-serif; margin: 0; background-color: #121212; color: #E0E0E0; }
        .nav-bar { background-color: #212121; padding: 15px 0; border-bottom: 1px solid #333; }
        .nav-link { color: #CCC; text-decoration: none; padding: 0 15px; font-weight: bold; }
        .nav-link.active { color: #64ffda; border-bottom: 3px solid #64ffda; }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        
        .search-section { background: #212121; padding: 20px; border-radius: 12px; border: 1px solid #444; margin-bottom: 20px; }
        .search-row { display: flex; gap: 15px; align-items: center; margin-bottom: 15px; flex-wrap: wrap;}
        .search-row:last-child { margin-bottom: 0; }
        
        select, button { padding: 10px 15px; border-radius: 6px; border: 1px solid #555; background: #333; color: #FFF; }
        button { background: #64ffda; color: #121212; font-weight: bold; border: none; cursor: pointer; }
        .label-text { font-weight: bold; color: #CCC; margin-right: 5px; }

        .chart-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .chart-card { background: #212121; padding: 20px; border-radius: 12px; border: 1px solid #444; height: 400px; }
        .chart-title { text-align: center; font-size: 18px; font-weight: bold; margin-bottom: 15px; color: #FFF; }

        .data-table { width: 100%; border-collapse: collapse; background: #212121; border-radius: 8px; overflow: hidden; }
        .data-table th { background: #333; padding: 12px; text-align: center; border-bottom: 1px solid #555; color: #FFF; }
        .data-table td { padding: 12px; text-align: center; border-bottom: 1px solid #444; color: #CCC; }
        
        .up { color: #ff6b6b; }   
        .down { color: #4dabf7; } 
        .rank-text { color: #fcc419; font-weight: bold; } 
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

    <div class="search-section">
        <form method="GET">
            <div class="search-row">
                <span class="label-text">필터</span>
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
            </div>

            <div class="search-row">
                <span class="label-text">선수</span>
                <select name="player_id" style="width: 300px;">
                    <?php if(empty($players)): ?>
                        <option value="0">조건에 맞는 선수가 없습니다</option>
                    <?php else: ?>
                        <option value="0" disabled <?php echo ($selected_player_id == 0) ? 'selected' : ''; ?>>선수를 선택하세요</option>
                        <?php foreach($players as $p): ?>
                            <option value="<?php echo $p['player_id']; ?>" <?php echo ($selected_player_id == $p['player_id'] ? 'selected' : ''); ?>>
                                <?php echo $p['name']; ?> (<?php echo $p['team_name']; ?> | <?php echo $p['pos_category']; ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <button type="submit" style="background:#fcc419; color:#000;">분석하기</button>
            </div>
        </form>
    </div>

    <?php if($selected_player_id > 0 && !empty($trend_data)): ?>
    <h3 style="color: #64ffda; margin-bottom: 20px; text-align:center;">
        <?php echo $player_name; ?>의 성장 지표
    </h3>

    <div class="chart-grid">
        <div class="chart-card">
            <div class="chart-title">연봉 및 팀 내 순위</div>
            <canvas id="salaryRankChart"></canvas>
        </div>
        <div class="chart-card">
            <div class="chart-title">성적(WAR) 변화</div>
            <canvas id="warChart"></canvas>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>연도</th>
                <th>연봉 (만원)</th>
                <th>팀 내 연봉 순위</th>
                <th>WAR (성적)</th>
                <th>WAR 변화량 (전년 대비)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($trend_data as $row): ?>
            <tr>
                <td><?php echo $row['year']; ?></td>
                <td><?php echo number_format($row['salary']); ?></td>
                <td class="rank-text"><?php echo $row['team_rank']; ?>위</td>
                <td><?php echo number_format($row['total_war'], 2); ?></td>
                <td>
                    <?php 
                        $diff = $row['war_diff'];
                        if ($diff > 0) echo "<span class='up'>▲ " . $diff . "</span>";
                        elseif ($diff < 0) echo "<span class='down'>▼ " . abs($diff) . "</span>";
                        else echo "-";
                    ?>
                </td>
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
    Chart.register(ChartDataLabels);

    const labels = <?php echo json_encode($chart_labels); ?>;
    const ranks = <?php echo json_encode($data_rank); ?>;

    // [차트 1] 연봉(막대) + 순위(텍스트)
    const ctx1 = document.getElementById('salaryRankChart').getContext('2d');
    new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: '연봉 (만원)',
                data: <?php echo json_encode($data_salary); ?>,
                backgroundColor: '#64ffda',
                barPercentage: 0.6,
                datalabels: {
                    align: 'end',
                    anchor: 'end',
                    color: '#fff',
                    font: { weight: 'bold', size: 11 },
                    formatter: function(value) { return value.toLocaleString(); }
                }
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: { top: 30 } },
            scales: {
                x: { ticks: { color: '#ccc' }, grid: { color: '#444' } },
                y: { ticks: { color: '#64ffda' }, grid: { color: '#444' }, title: { display: true, text: '연봉', color: '#64ffda' } }
            },
            plugins: {
                legend: { display: false },
                tooltip: { enabled: true } 
            },
            animation: {
                onComplete: function() {
                    const chart = this;
                    const ctx = chart.ctx;
                    ctx.font = 'bold 15px Pretendard';
                    ctx.fillStyle = '#fcc419'; 
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'bottom';

                    chart.data.datasets.forEach(function(dataset, i) {
                        const meta = chart.getDatasetMeta(i);
                        meta.data.forEach(function(bar, index) {
                            const rankText = ranks[index] + '위';
                            ctx.fillText(rankText, bar.x, bar.y - 25); 
                        });
                    });
                }
            }
        }
    });

    // [차트 2] WAR 꺾은선 (막대 제거됨)
    const ctx2 = document.getElementById('warChart').getContext('2d');
    new Chart(ctx2, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'WAR (성적)',
                data: <?php echo json_encode($data_war); ?>,
                borderColor: '#ff6b6b',
                backgroundColor: 'rgba(255, 107, 107, 0.2)',
                borderWidth: 3,
                pointRadius: 5,
                pointBackgroundColor: '#ff6b6b',
                fill: true, // 영역 채우기
                tension: 0.3,
                datalabels: {
                    align: 'top',
                    anchor: 'end',
                    color: '#ff6b6b',
                    font: { weight: 'bold', size: 13 },
                    offset: 5,
                    formatter: function(value) { return value; }
                }
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: { top: 20 } },
            scales: {
                x: { ticks: { color: '#ccc' }, grid: { color: '#444' } },
                y: { ticks: { color: '#ff6b6b' }, grid: { color: '#444' }, title: { display: true, text: 'WAR', color: '#ff6b6b' } }
            },
            plugins: { legend: { display: false }, tooltip: { enabled: true } }
        }
    });
    <?php endif; ?>
</script>

</body>
</html>