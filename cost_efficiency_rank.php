<?php
// =================================================================
// cost_efficiency_rank.php: 선수 가성비 랭킹 분석 페이지
// 과제 요구사항 2-(4)-3) Ranking Function 구현
// =================================================================

// 1. DB 연결 설정 파일을 불러옵니다.
include 'db_connect.php'; 

// 2. 사용자 입력 변수 초기값 설정
// DB에 데이터가 있다는 가정 하에, 기본 시즌 ID는 11 (2025)로 유지
$current_season_id = 11; 
$min_ePA = 100;          
$position_filter = 'ALL';

// 3. 사용자 입력 값 처리 (POST 요청 확인)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // ✅ 시즌 ID 값 (HTML 폼에서 season_id를 직접 넘긴다고 가정)
    if (isset($_POST['season']) && is_numeric($_POST['season'])) {
        $current_season_id = intval($_POST['season']);
    }
    
    // 최소 타석
    if (isset($_POST['min_ePA']) && is_numeric($_POST['min_ePA'])) {
        $min_ePA = intval($_POST['min_ePA']);
    }
    // 포지션 필터링
    if (isset($_POST['pos_category']) && $_POST['pos_category'] !== 'ALL') {
        $position_filter = $_POST['pos_category'];
    }
}

// 4. DB에서 필터링 옵션 (시즌, 포지션) 조회
$positions_option = [];
$seasons_option = [];

// 4-1. 포지션 옵션 조회 (디버깅 출력 제거)
$pos_query = "SELECT DISTINCT pos_category FROM position_detail WHERE pos_category != ''";
$pos_result = $conn->query($pos_query);

if ($pos_result) {
    while ($row = $pos_result->fetch_assoc()) {
        $positions_option[] = $row['pos_category'];
    }
}

// 4-2. 시즌 옵션 조회 (디버깅 출력 제거)
$season_query = "SELECT season_id, year FROM season ORDER BY year DESC";
$season_result = $conn->query($season_query);

if ($season_result) {
    while ($row = $season_result->fetch_assoc()) {
        $seasons_option[$row['season_id']] = $row['year'];
    }
    // 기본 시즌 ID가 유효한지 확인 (기본값 11이 없을 경우 대비)
    if (!array_key_exists($current_season_id, $seasons_option) && !empty($seasons_option)) {
         // 기본값이 없으면 목록 중 가장 최신 시즌을 사용
        $current_season_id = array_key_first($seasons_option);
    }
}


// 5. 가성비 랭킹 쿼리 준비 (Windowing Function RANK() 사용)
// P.weight, P.height 컬럼은 사용되지 않으므로 쿼리 수정 필요 없음.
$sql = "SELECT P.name, T.team_name, PD.pos_category, S.amount AS salary, A.oWAR, A.dWAR,
               (A.oWAR + A.dWAR) / S.amount * 100000 AS value_index,
               RANK() OVER (ORDER BY (A.oWAR + A.dWAR) / S.amount DESC) AS ranking
        FROM player P
        JOIN team T ON P.team_id = T.team_id
        JOIN position_detail PD ON P.pos_id = PD.pos_id
        JOIN salary S ON P.player_id = S.player_id
        JOIN attack_stat A ON P.player_id = A.player_id AND S.season_id = A.season_id
        WHERE A.season_id = ? 
          AND A.ePA >= ?
          AND PD.pos_category LIKE ? 
        ORDER BY ranking";

// 6. Prepared Statement 실행
$stmt = $conn->prepare($sql);
// 'ALL' 필터 시 DB에서 모든 것을 찾도록 '%%'로 변환
$final_pos_filter = ($position_filter === 'ALL') ? '%%' : $position_filter;

// bind_param: i (integer: season_id), i (integer: min_ePA), s (string: pos_category)
$stmt->bind_param("iis", $current_season_id, $min_ePA, $final_pos_filter);
$stmt->execute();
$result = $stmt->get_result();

$rankings = [];
while ($row = $result->fetch_assoc()) {
    $rankings[] = $row;
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KBO 선수 가성비 랭킹</title>
    <style>
        /* [무채색 디자인 설정] */
        body { 
            font-family: 'Inter', sans-serif; 
            margin: 0; 
            background-color: #121212; /* 어두운 배경 */
            color: #E0E0E0; /* 밝은 회색 텍스트 */
        }
        .nav-bar {
            background-color: #212121; /* 내비게이션 배경 */
            padding: 15px 0;
            border-bottom: 1px solid #333333;
        }
        .nav-link {
            color: #CCCCCC; /* 비활성 메뉴 */
            text-decoration: none; 
            padding: 0 15px; 
            transition: color 0.3s, border-bottom 0.3s;
        }
        .nav-link:hover {
            color: #FFFFFF; /* 호버 시 흰색 */
        }
        .nav-link.active {
            color: #64ffda; /* 강조색: 연한 청록색 */
            font-weight: bold; 
            border-bottom: 3px solid #64ffda;
        }
        .container { 
            max-width: 1100px; 
            margin: 30px auto; 
            background: #212121; /* 컨테이너 배경 */
            padding: 30px; 
            border-radius: 8px; 
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.5); 
        }
        h2, h3 { 
            color: #FFFFFF; /* 제목 흰색 */
            border-bottom: 2px solid #333333; 
            padding-bottom: 10px; 
            margin-bottom: 20px; 
        }
        .filter-form { 
            background-color: #2c2c2c; /* 필터 폼 배경 */
            border: 1px solid #444444; 
            padding: 20px; 
            border-radius: 6px; 
            margin-bottom: 30px; 
        }
        label { 
            color: #CCCCCC;
            margin-right: 10px; 
            font-weight: 600; 
        }
        select, input[type="number"], button { 
            padding: 8px 12px; 
            border: 1px solid #555555; 
            border-radius: 4px; 
            margin-right: 15px;
            background-color: #333333; /* 입력 필드 배경 */
            color: #FFFFFF;
        }
        button { 
            background-color: #64ffda; /* 강조색 버튼 */
            color: #121212; /* 버튼 텍스트는 어둡게 */
            border: none; 
            cursor: pointer; 
            font-weight: bold;
            transition: background-color 0.3s; 
        }
        button:hover { 
            background-color: #92ffe6; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
            font-size: 0.9em; 
        }
        th, td { 
            border: 1px solid #444444; 
            padding: 12px; 
            text-align: center; 
        }
        th { 
            background-color: #333333; /* 헤더 배경 */
            color: #FFFFFF; 
        }
        tr:nth-child(even) { 
            background-color: #2c2c2c; /* 짝수 행 배경 */
        }
        tr:nth-child(odd) {
             background-color: #212121; /* 홀수 행 배경 */
        }
        td:nth-child(9) { 
            font-weight: bold; 
            color: #64ffda; /* 가성비 지수 강조색 */
        } 
        .no-results { 
            text-align: center; 
            padding: 20px; 
            background-color: #333333; 
            color: #FFFFFF; 
            border-radius: 6px; 
        }

        .formula-box {
            background-color: #333333; /* 어두운 배경 */
            border: 1px solid #555555;
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .formula-box h3 {
            color: #64ffda; /* 강조색으로 제목 */
            border-bottom: 1px solid #555555;
            padding-bottom: 5px;
            margin-top: 0;
            margin-bottom: 15px;
        }

        .formula-text {
            font-size: 1.15em;
            font-weight: bold;
            color: #FFFFFF;
        }

        .formula-note {
            font-size: 0.9em;
            color: #AAAAAA; /* 연한 회색 */
            margin-top: 10px;
            padding-top: 5px;
            border-top: 1px dashed #555555;
        }
            </style>
</head>
<body>

<nav class="nav-bar">
    <div style="max-width: 1100px; margin: auto; display: flex; justify-content: space-between;">
        <a href="/team17/cost_efficiency_rank.php" class="nav-link active">가성비 랭킹</a>
        
        <a href="/team17/player_detail.php" class="nav-link">선수 상세 기록</a>
        <a href="/team17/fa_vote.php" class="nav-link">FA 연봉 예측</a>
        
        <a href="/team17/analysis_window.php" class="nav-link">선수 성장 추이</a>
        <a href="/team17/analysis_aggregate.php" class="nav-link">팀/포지션별 평균</a>
        <a href="/team17/analysis_rollup.php" class="nav-link">계층별 효율 분석</a>
        
        <a href="/team17/attack_stat.php" class="nav-link">타격 기록 조회</a>
        <a href="/team17/defense_stat.php" class="nav-link">수비 기록 조회</a>
    </div>
</nav>

<div class="container">
    <h2>KBO 선수 가성비 랭킹 분석</h2>

    <form method="POST" action="cost_efficiency_rank.php" class="filter-form">
        <h3>필터링 조건 입력 (Windowing Function RANK())</h3>
        
        <label for="season">시즌 선택:</label>
        <select name="season" id="season" required>
            <option value="" disabled selected>-- 선택 --</option>
            <?php foreach ($seasons_option as $id => $year): ?>
                <option value="<?php echo $id; ?>" <?php echo ($id == $current_season_id) ? 'selected' : ''; ?>>
                    <?php echo $year; ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <label for="min_ePA" style="margin-left: 20px;">최소 타석 (ePA):</label>
        <input type="number" name="min_ePA" id="min_ePA" value="<?php echo $min_ePA; ?>" required style="width: 80px;">
        
        <label for="pos_category" style="margin-left: 20px;">포지션 카테고리 필터링:</label>
        <select name="pos_category" id="pos_category">
            <option value="ALL" <?php echo ($position_filter === 'ALL') ? 'selected' : ''; ?>>전체</option>
            <?php 
            foreach ($positions_option as $pos): ?>
                <option value="<?php echo htmlspecialchars($pos); ?>" <?php echo ($pos === $position_filter) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($pos); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <button type="submit" style="margin-left: 20px;">랭킹 조회</button>
    </form>

    <hr style="border-top: 1px solid #444444;">

    <div class="formula-box">
    <h3>가성비 산출 공식</h3>
    <p>
        <span class="formula-text">
            가성비 지수 = (oWAR + dWAR) / 연봉 (만원) × 100,000
        </span>
    </p>
    <p class="formula-note">
        * 연봉은 만원 단위로 계산되며, 지수 값을 보기 쉽게 보정하기 위해 100,000을 곱합니다.
    </p>
    </div>
    
    <hr style="border-top: 1px solid #444444;">

    <h3>순위 테이블 (총 <?php echo count($rankings); ?>명)</h3>
    <table>
        <thead>
            <tr>
                <th>순위</th>
                <th>선수명</th>
                <th>소속팀</th>
                <th>포지션 카테고리</th>
                <th>연봉 (만원)</th>
                <th>oWAR</th>
                <th>dWAR</th>
                <th>가성비 지수</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            if (empty($rankings)) {
                echo '<tr><td colspan="8" class="no-results">조회된 선수가 없습니다. 조건을 변경해 주세요.</td></tr>';
            } else {
                foreach ($rankings as $rank): 
            ?>
            <tr>
                <td><?php echo $rank['ranking']; ?></td>
                <td><?php echo htmlspecialchars($rank['name']); ?></td>
                <td><?php echo htmlspecialchars($rank['team_name']); ?></td>
                <td><?php echo htmlspecialchars($rank['pos_category']); ?></td>
                <td><?php echo number_format($rank['salary']); ?></td>
                <td><?php echo number_format($rank['oWAR'], 2); ?></td>
                <td><?php echo number_format($rank['dWAR'], 2); ?></td>
                <td>**<?php echo number_format($rank['value_index'], 2); ?>**</td>
            </tr>
            <?php 
                endforeach; 
            }
            ?>
        </tbody>
    </table>
</div>
</body>
</html>