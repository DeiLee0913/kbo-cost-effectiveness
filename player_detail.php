<?php
// =================================================================
// player_detail.php: 선수 상세 기록 페이지 (수정됨)
// =================================================================

include 'db_connect.php';

// 1. 변수 초기화
$player_id = isset($_GET['player_id']) ? intval($_GET['player_id']) : 0;
$search_keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';
$team_filter = isset($_GET['team']) ? $_GET['team'] : 'ALL';
$pos_filter = isset($_GET['pos']) ? $_GET['pos'] : 'ALL';

// 2. 필터 옵션 가져오기
$teams = [];
$positions = [];
$team_res = $conn->query("SELECT team_id, team_name FROM team");
while($r = $team_res->fetch_assoc()) { $teams[$r['team_id']] = $r['team_name']; }
$pos_res = $conn->query("SELECT DISTINCT pos_category FROM position_detail");
while($r = $pos_res->fetch_assoc()) { $positions[] = $r['pos_category']; }

// 3. 검색 로직
$search_results = [];
if ($search_keyword || $team_filter !== 'ALL' || $pos_filter !== 'ALL') {
    $search_sql = "SELECT p.player_id, p.name, t.team_name, pd.pos_category 
                   FROM player p 
                   LEFT JOIN team t ON p.team_id = t.team_id 
                   LEFT JOIN position_detail pd ON p.pos_id = pd.pos_id 
                   WHERE 1=1";
    
    if ($search_keyword) {
        $search_sql .= " AND p.name LIKE '%" . $conn->real_escape_string($search_keyword) . "%'";
    }
    if ($team_filter !== 'ALL') {
        $search_sql .= " AND p.team_id = " . intval($team_filter);
    }
    if ($pos_filter !== 'ALL') {
        $search_sql .= " AND pd.pos_category = '" . $conn->real_escape_string($pos_filter) . "'";
    }
    $res = $conn->query($search_sql);
    while($r = $res->fetch_assoc()) { $search_results[] = $r; }
}

// 4. 상세 정보 로직
$player_info = null;
$attack_stats = [];
$defense_stats = [];

if ($player_id > 0) {
    // 4-1. 선수 기본 정보
    $info_sql = "SELECT p.*, t.team_name, pd.pos_category 
                 FROM player p 
                 LEFT JOIN team t ON p.team_id = t.team_id 
                 LEFT JOIN position_detail pd ON p.pos_id = pd.pos_id 
                 WHERE p.player_id = $player_id";
    $player_info = $conn->query($info_sql)->fetch_assoc();

    // 4-2. 최근 5년(2021-2025) 타격 기록 (요청하신 컬럼 순서 반영)
    $att_sql = "SELECT s.year, a.AVG, a.G, a.ePA, a.AB, a.H, a.HR, a.RBI, a.SB, a.OBP, a.SLG, a.WRC, a.OWAR 
                FROM attack_stat a 
                JOIN season s ON a.season_id = s.season_id 
                WHERE a.player_id = $player_id AND s.year BETWEEN 2021 AND 2025 
                ORDER BY s.year DESC";
    $att_res = $conn->query($att_sql);
    while($r = $att_res->fetch_assoc()) { $attack_stats[] = $r; }

    // 4-3. 최근 5년(2021-2025) 수비 기록
    $def_sql = "SELECT s.year, d.* FROM defense_stat d 
                JOIN season s ON d.season_id = s.season_id 
                WHERE d.player_id = $player_id AND s.year BETWEEN 2021 AND 2025 
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
        /* 공통 디자인 (다크모드) */
        body { font-family: 'Pretendard', sans-serif; margin: 0; background-color: #121212; color: #E0E0E0; }
        .nav-bar { background-color: #212121; padding: 15px 0; border-bottom: 1px solid #333; }
        .nav-link { color: #CCC; text-decoration: none; padding: 0 15px; font-weight: bold; }
        .nav-link.active { color: #64ffda; border-bottom: 3px solid #64ffda; }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }

        /* 검색 박스 스타일 */
        .search-box { background: #212121; padding: 40px; border-radius: 12px; text-align: center; margin-bottom: 30px; border: 1px solid #333; }
        .search-title { font-size: 24px; font-weight: bold; margin-bottom: 20px; color: #FFF; }
        select, input[type="text"] { padding: 12px; border-radius: 6px; border: 1px solid #555; background: #333; color: #FFF; margin-right: 10px; }
        input[type="text"] { width: 300px; }
        .btn-search { background: #64ffda; color: #121212; border: none; padding: 12px 24px; border-radius: 6px; font-weight: bold; cursor: pointer; }
        .btn-search:hover { background: #92ffe6; }

        /* 검색 결과 리스트 */
        .result-list { list-style: none; padding: 0; margin-top: 20px; text-align: left; }
        .result-item { background: #2c2c2c; padding: 15px; margin-bottom: 10px; border-radius: 8px; cursor: pointer; display: flex; justify-content: space-between; transition: 0.2s; }
        .result-item:hover { background: #444; }
        .result-item a { text-decoration: none; color: #FFF; width: 100%; display: block; }

        /* 상세 페이지 - 프로필 카드 */
        .profile-card { background: #212121; padding: 30px; border-radius: 12px; display: flex; align-items: center; gap: 30px; margin-bottom: 30px; border: 1px solid #444; }
        .profile-img { width: 120px; height: 120px; background: #444; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 40px; color: #888; }
        .profile-info h2 { margin: 0 0 10px 0; font-size: 32px; color: #FFF; }
        .profile-info p { margin: 5px 0; color: #CCC; font-size: 16px; }
        .tag { background: #333; padding: 5px 10px; border-radius: 4px; font-size: 14px; margin-right: 10px; color: #64ffda; border: 1px solid #64ffda; }

        /* 스탯 테이블 섹션 */
        .stat-section { margin-bottom: 40px; }
        .section-header { display: flex; align-items: center; margin-bottom: 15px; }
        .badge-box { background: #64ffda; color: #121212; padding: 8px 16px; border-radius: 6px; font-weight: bold; margin-right: 15px; }
        .section-title { font-size: 20px; font-weight: bold; color: #FFF; }
        
        table.stat-table { width: 100%; border-collapse: collapse; background: #212121; border-radius: 8px; overflow: hidden; }
        table.stat-table th { background: #333; color: #FFF; padding: 12px; text-align: center; border-bottom: 1px solid #444; white-space: nowrap;}
        table.stat-table td { padding: 12px; text-align: center; border-bottom: 1px solid #444; color: #E0E0E0; }
        table.stat-table tr:last-child td { border-bottom: none; }
    </style>
</head>
<body>

<nav class="nav-bar">
    <div style="max-width: 1100px; margin: auto; display: flex; justify-content: space-between;">
        <a href="/team17/cost_efficiency_rank.php" class="nav-link">가성비 랭킹</a>
        <a href="/team17/player_detail.php" class="nav-link active">선수별 페이지</a>
        <a href="/team17/fa_vote.php" class="nav-link">선수 연봉 투표</a>
        <a href="/team17/analysis_window.php" class="nav-link">선수 성장 추이</a>
        <a href="/team17/analysis_aggregate.php" class="nav-link">팀/포지션별 연봉</a>
        <a href="/team17/analysis_rollup.php" class="nav-link">연봉 계층별 효율</a>
        <a href="/team17/attack_stat.php" class="nav-link">타격 기록</a>
        <a href="/team17/defense_stat.php" class="nav-link">수비 기록</a>
    </div>
</nav>

<div class="container">

    <div class="search-box">
        <div class="search-title">선수 검색</div>
        <form method="GET" action="player_detail.php">
            <select name="team">
                <option value="ALL">팀 전체</option>
                <?php foreach($teams as $id => $name): ?>
                    <option value="<?php echo $id; ?>" <?php echo ($team_filter == $id) ? 'selected' : ''; ?>>
                        <?php echo $name; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="pos">
                <option value="ALL">포지션 전체</option>
                <?php foreach($positions as $pos): ?>
                    <option value="<?php echo $pos; ?>" <?php echo ($pos_filter == $pos) ? 'selected' : ''; ?>>
                        <?php echo $pos; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="keyword" placeholder="선수 이름을 입력하세요" value="<?php echo htmlspecialchars($search_keyword); ?>">
            <button type="submit" class="btn-search">검색</button>
        </form>

        <?php if (!empty($search_results) && !$player_info): ?>
            <ul class="result-list">
                <?php foreach($search_results as $p): ?>
                    <li class="result-item">
                        <a href="player_detail.php?player_id=<?php echo $p['player_id']; ?>">
                            <strong><?php echo htmlspecialchars($p['name']); ?></strong> 
                            <span style="color:#888; margin-left:10px;">
                                <?php echo $p['team_name']; ?> | <?php echo $p['pos_category']; ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <?php if ($player_info): ?>
        
        <div class="profile-card">
            <div class="profile-img">IMG</div>
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($player_info['name']); ?></h2>
                <p>
                    <span class="tag"><?php echo $player_info['team_name']; ?></span>
                    <span class="tag"><?php echo $player_info['pos_category']; ?></span>
                </p>
                <p><strong>생년월일:</strong> <?php echo $player_info['birth_date']; ?></p>
                <p><strong>신체조건:</strong> <?php echo $player_info['height']; ?>cm / <?php echo $player_info['weight']; ?>kg</p>
                <p><strong>입단 계약금:</strong> <?php echo number_format($player_info['signing_bonus']); ?>만원</p>
                <p><strong>경력:</strong> <?php echo htmlspecialchars($player_info['career']); ?></p>
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
                        <th>홈런</th>
                        <th>타점</th>
                        <th>도루</th>
                        <th>출루율</th>
                        <th>장타율</th>
                        <th>wRC</th>
                        <th>WAR</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($attack_stats)): ?>
                        <tr><td colspan="13">기록이 없습니다.</td></tr>
                    <?php else: ?>
                        <?php foreach($attack_stats as $stat): ?>
                        <tr>
                            <td><?php echo $stat['year']; ?></td>
                            <td style="color:#64ffda; font-weight:bold;"><?php echo $stat['AVG']; ?></td>
                            <td><?php echo $stat['G']; ?></td>
                            <td><?php echo $stat['ePA']; ?></td>
                            <td><?php echo $stat['AB']; ?></td>
                            <td><?php echo $stat['H']; ?></td>
                            <td><?php echo $stat['HR']; ?></td>
                            <td><?php echo $stat['RBI']; ?></td>
                            <td><?php echo $stat['SB']; ?></td>
                            <td><?php echo $stat['OBP']; ?></td>
                            <td><?php echo $stat['SLG']; ?></td>
                            <td><?php echo $stat['WRC']; ?></td>
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
                        <th>RF9</th>
                        <th>dWAR</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($defense_stats)): ?>
                        <tr><td colspan="7">기록이 없습니다.</td></tr>
                    <?php else: ?>
                        <?php foreach($defense_stats as $stat): ?>
                        <tr>
                            <td><?php echo $stat['year']; ?></td>
                            <td><?php echo $stat['G']; ?></td>
                            <td><?php echo $stat['GS']; ?></td>
                            <td><?php echo $stat['ASS']; ?></td>
                            <td><?php echo $stat['E']; ?></td>
                            <td><?php echo $stat['RF9']; ?></td>
                            <td style="color:#64ffda; font-weight:bold;"><?php echo $stat['dWAR']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</div>

</body>
</html>