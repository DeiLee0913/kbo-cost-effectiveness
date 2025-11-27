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

$player_info = null;
$attack_stats = [];
$defense_stats = [];

if ($selected_player_id > 0) {
    $info_sql = "SELECT p.*, t.team_name, pd.pos_category 
                 FROM player p 
                 LEFT JOIN team t ON p.team_id = t.team_id 
                 LEFT JOIN position_detail pd ON p.pos_id = pd.pos_id 
                 WHERE p.player_id = $selected_player_id";
    $player_info = $conn->query($info_sql)->fetch_assoc();

    $att_sql = "SELECT s.year, a.AVG, a.G, a.ePA, a.AB, a.H, a.RBI, a.SB, a.OBP, a.OWAR 
                FROM attack_stat a 
                JOIN season s ON a.season_id = s.season_id 
                WHERE a.player_id = $selected_player_id AND s.year BETWEEN 2021 AND 2025 
                ORDER BY s.year DESC";
    $att_res = $conn->query($att_sql);
    while($r = $att_res->fetch_assoc()) { $attack_stats[] = $r; }

    $def_sql = "SELECT s.year, d.* FROM defense_stat d 
                JOIN season s ON d.season_id = s.season_id 
                WHERE d.player_id = $selected_player_id AND s.year BETWEEN 2021 AND 2025 
                ORDER BY s.year DESC";
    $def_res = $conn->query($def_sql);
    while($r = $def_res->fetch_assoc()) { $defense_stats[] = $r; }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>선수 상세 기록</title>
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

        .profile-card { background: #212121; padding: 30px; border-radius: 12px; border: 1px solid #444; margin-bottom: 30px; }
        .profile-header { border-bottom: 1px solid #444; padding-bottom: 15px; margin-bottom: 15px; }
        .profile-header h2 { margin: 0; font-size: 32px; color: #FFF; display: inline-block; margin-right: 15px; }
        .tag { background: #333; padding: 5px 10px; border-radius: 4px; font-size: 14px; margin-right: 5px; color: #64ffda; border: 1px solid #64ffda; vertical-align: middle; }
        .profile-info p { margin: 8px 0; color: #CCC; font-size: 16px; }
        .profile-info strong { color: #FFF; width: 100px; display: inline-block; }

        .stat-section { margin-bottom: 40px; }
        .section-header { display: flex; align-items: center; margin-bottom: 15px; }
        .badge-box { background: #64ffda; color: #121212; padding: 8px 16px; border-radius: 6px; font-weight: bold; margin-right: 15px; }
        .section-title { font-size: 20px; font-weight: bold; color: #FFF; }
        
        table.stat-table { width: 100%; border-collapse: collapse; background: #212121; border-radius: 8px; overflow: hidden; }
        table.stat-table th { background: #333; color: #FFF; padding: 12px; text-align: center; border-bottom: 1px solid #444; white-space: nowrap;}
        table.stat-table td { padding: 12px; text-align: center; border-bottom: 1px solid #444; color: #E0E0E0; }
        table.stat-table tr:last-child td { border-bottom: none; }
        
        .empty-msg { text-align:center; padding: 80px; color: #777; font-size: 1.2em; }
    </style>
</head>
<body>

<nav class="nav-bar">
    <div style="max-width: 1100px; margin: auto; display: flex; justify-content: space-between;">
        <a href="/team17/cost_efficiency_rank.php" class="nav-link">가성비 랭킹</a>
        <a href="/team17/player_detail.php" class="nav-link active">선수별 페이지</a>
        <a href="/team17/fa_vote.php" class="nav-link">선수 연봉 투표</a>
        <a href="/team17/player_growth.php" class="nav-link">선수 성장 추이</a>
        <a href="/team17/analysis_aggregate.php" class="nav-link">팀/포지션별 연봉</a>
        <a href="/team17/analysis_rollup.php" class="nav-link">연봉 계층별 효율</a>
        <a href="/team17/attack_stat.php" class="nav-link">타격 기록</a>
        <a href="/team17/defense_stat.php" class="nav-link">수비 기록</a>
    </div>
</nav>

<div class="container">

    <div class="search-section">
        <form method="GET">
            <div class="search-row">
                <span class="label-text">1. 필터</span>
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
                <span class="label-text">2. 선택</span>
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
                <button type="submit" style="background:#fcc419; color:#000;">조회하기</button>
            </div>
        </form>
    </div>

    <?php if ($player_info): ?>
        
        <div class="profile-card">
            <div class="profile-header">
                <h2><?php echo htmlspecialchars($player_info['name']); ?></h2>
                <span class="tag"><?php echo $player_info['team_name']; ?></span>
                <span class="tag"><?php echo $player_info['pos_category']; ?></span>
            </div>
            <div class="profile-info">
                <p><strong>생년월일</strong> <?php echo $player_info['birth_date']; ?></p>
                <p><strong>등번호</strong> No.<?php echo $player_info['uniform_number']; ?></p>
                <p><strong>지명 순위</strong> <?php echo htmlspecialchars($player_info['draft_rank']); ?></p>
                <p><strong>경력</strong> <?php echo htmlspecialchars($player_info['career']); ?></p>
            </div>
        </div>

        <div class="stat-section">
            <div class="section-header">
                <div class="badge-box">공격</div>
                <div class="section-title">최근 5시즌 타격 기록</div>
            </div>
            <table class="stat-table">
                <thead>
                    <tr>
                        <th>시즌</th>
                        <th>타율</th>
                        <th>경기</th>
                        <th>타석</th>
                        <th>타수</th>
                        <th>안타</th>
                        <th>타점</th>
                        <th>도루</th>
                        <th>출루율</th>
                        <th>WAR</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($attack_stats)): ?>
                        <tr><td colspan="10">기록이 없습니다.</td></tr>
                    <?php else: ?>
                        <?php foreach($attack_stats as $stat): ?>
                        <tr>
                            <td><?php echo $stat['year']; ?></td>
                            <td style="color:#64ffda; font-weight:bold;"><?php echo $stat['AVG']; ?></td>
                            <td><?php echo $stat['G']; ?></td>
                            <td><?php echo $stat['ePA']; ?></td>
                            <td><?php echo $stat['AB']; ?></td>
                            <td><?php echo $stat['H']; ?></td>
                            <td><?php echo $stat['RBI']; ?></td>
                            <td><?php echo $stat['SB']; ?></td>
                            <td><?php echo $stat['OBP']; ?></td>
                            <td><?php echo $stat['OWAR']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="stat-section">
            <div class="section-header">
                <div class="badge-box" style="background:#00d2d3;">수비</div>
                <div class="section-title">최근 5시즌 수비 기록</div>
            </div>
            <table class="stat-table">
                <thead>
                    <tr>
                        <th>시즌</th>
                        <th>경기</th>
                        <th>선발</th>
                        <th>보살(ASS)</th>
                        <th>실책(E)</th>
                        <th>RF(아웃카운트관여도)</th>
                        <th>실책득점기여(ErrR)</th>
                        <th>수비득점기여(RAA)</th>
                        <th>WAA(수비 승리기여도)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($defense_stats)): ?>
                        <tr><td colspan="9">기록이 없습니다.</td></tr>
                    <?php else: ?>
                        <?php foreach($defense_stats as $stat): ?>
                        <tr>
                            <td><?php echo $stat['year']; ?></td>
                            <td><?php echo $stat['G']; ?></td>
                            <td><?php echo $stat['GS']; ?></td>
                            <td><?php echo $stat['ASS']; ?></td>
                            <td><?php echo $stat['E']; ?></td>
                            <td><?php echo $stat['RF9']; ?></td>
                            <td><?php echo $stat['Err_RAA']; ?></td>
                            <td><?php echo $stat['RAA']; ?></td>
                            <td><?php echo $stat['WAAwoPOS']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>
        <div class="empty-msg">
            선수를 선택하고 <strong>[조회하기]</strong> 버튼을 눌러주세요.
        </div>
    <?php endif; ?>

</div>

</body>
</html>