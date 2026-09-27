<?php
/**
 * 教导大队管理后台 API（PHP版）
 * 替代原Node.js后端，适用于宝塔面板部署
 *
 * 部署说明：
 * 1. 将整个 api 文件夹上传到网站根目录
 * 2. 确保 data/ 目录有写入权限（宝塔面板 → 文件 → 权限设为 755 或 777）
 * 3. 确保 backups/ 目录有写入权限
 */

// 飞书机器人通知辅助
require_once __DIR__ . '/feishu_bot.php';

header('Content-Type: application/json; charset=utf-8');
// CORS：限制为同源请求（部署时可根据实际情况调整）
$allowedOrigins = ['*']; // 生产环境建议设为具体域名，如 ['https://www.jiaodao.fun']
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if (in_array($origin, $allowedOrigins) || in_array('*', $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . (in_array('*', $allowedOrigins) ? '*' : $origin));
}
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Operator-Name, X-Operator-OpenId, X-Operator-Method');
header('Access-Control-Max-Age: 86400');
// 防止MIME嗅探攻击
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ==================== 配置 ====================
define('DATA_DIR', __DIR__ . '/../api_data');
define('BACKUP_DIR', DATA_DIR . '/backups');
define('FRONTEND_NEWS_PATH', __DIR__ . '/../news_data.json');
define('MAX_FULL_BACKUPS', 10);
define('MAX_SINGLE_BACKUPS', 20);

// 确保目录存在
foreach ([DATA_DIR, BACKUP_DIR] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// ==================== 默认数据 ====================
$DEFAULT_DATA = [
    'newsData' => [],
    'currentLeaders' => [
        ['id' => 1, 'name' => '徐京都', 'post' => '校长助理、学生工作处处长', 'desc' => '徐京都，男，汉族，中共党员，现任湖南涉外经济学院校长助理、主管学生工作部（处）。', 'photo' => 'leader1.jpg'],
        ['id' => 2, 'name' => '夏建平', 'post' => '武装部副部长、教导大队指导老师', 'desc' => '夏建平，男，汉族，中共党员，现任湖南涉外经济学院人民武装部副部长、军训领导小组办公室副主任、学生志愿教导大队指导老师。', 'photo' => 'leader2.jpg']
    ],
    'orgStructure' => [
        'departments' => [
            ['name' => '常务委员会', 'members' => ['何俊杰', '张钰', '陈启蕊', '郑佳旺']],
            ['name' => '综合办公室', 'members' => ['欧家', '潘妍妍']],
            ['name' => '宣传部', 'members' => ['艾彦廷', '廖敏婕']],
            ['name' => '拓展部', 'members' => ['黄一倩', '聂玲飞']],
            ['name' => '后勤部', 'members' => ['钟曼怡', '钱瑞鑫']],
            ['name' => '纪检部', 'members' => ['江珧', '桂祺欣']]
        ],
        'squadrons' => [
            ['name' => '凌云骨干队', 'leader' => '陈启蕊'],
            ['name' => '烽火一中队', 'leader' => '刘仕鹏'],
            ['name' => '雷霆二中队', 'leader' => '刘姿'],
            ['name' => '雪狼三中队', 'leader' => '单荣祺'],
            ['name' => '虎贲四中队', 'leader' => '李涛'],
            ['name' => '苍龙五中队', 'leader' => '江涵'],
            ['name' => '火麒六中队', 'leader' => '符森']
        ]
    ],
    'homeOverview' => '湖南涉外经济学院学生志愿教导大队是一支半军事化队伍，接受学校学生工作处（人民武装部）的领导，新生军训期间由军训领导小组直接领导管理。日常由学生工作处（人民武装部）直接领导管理，教导大队未经学校批准，不得随意出动，在执行任务过程中，必须组成分队或小组并服从指定带队人指挥，教导大队队员集体执行任务和训练时必须统一着军装、佩戴校徽。严禁非任务或训练期间穿着军服。

教导大队成立于2011年，是一支由武装部领导的军事化队伍。大队以"明理严军、自强不息"为队训，实行严格的半军事化管理，坚持纪律严明、训练刻苦、作风扎实、甘于奉献，是校园精神文明建设与国防教育工作的重要骨干力量，多次荣获省、校级表彰与高度认可。',
    'aboutText1' => '湖南涉外经济学院学生志愿教导大队是一支半军事化队伍，接受学校学生工作处（人民武装部）的领导，新生军训期间由军训领导小组直接领导管理。日常由学生工作处（人民武装部）直接领导管理。',
    'aboutText2' => '教导大队成立于2011年，是一支由武装部领导的军事化队伍。教导大队设有六个部门，分别为教导大队常务委员会、综合办公室、宣传部、拓展部、后勤部、纪检部。教导大队设有七个中队，分别为凌云骨干队；烽火一中队；雷霆二中队；雪狼三中队；虎贲四中队；苍龙五中队；火麒六中队。',
    'aboutText3' => '学生志愿教导大队的主要职能是：承担学校新生军训及国防安全教育，执行校园文明教育与劝导，协助执行校园秩序巡逻、重大活动值勤，每周举行校园升旗仪式。',
    'aboutText4' => '学生志愿教导大队的队员是由在校大一大二学生自主报名、教官推荐、经大队严格选拔，高强度集中训练之后组成。实行军事化管理，统一着装。教导大队严格实行考核淘汰制，以维护队伍的组织性和纪律性。',
    'aboutMotto' => '明理严军 · 自强不息',
    'sliderImages' => ['slide1.jpg', 'slide2.jpg', 'slide3.jpg', 'slide4.jpg', 'slide5.jpg'],
    'aboutImages' => ['pic1.jpg', 'pic2.jpg', 'pic3.jpg'],
    'albumImages' => [
        ['id' => 1, 'photo' => 'album1.jpg', 'desc' => '升旗仪式'],
        ['id' => 2, 'photo' => 'album2.jpg', 'desc' => '野外拉练'],
        ['id' => 3, 'photo' => 'album3.jpg', 'desc' => '授衔仪式'],
        ['id' => 4, 'photo' => 'album4.jpg', 'desc' => '拓展训练'],
        ['id' => 5, 'photo' => 'album5.jpg', 'desc' => '军事训练'],
        ['id' => 6, 'photo' => 'album6.jpg', 'desc' => '队员合影'],
        ['id' => 7, 'photo' => 'album7.jpg', 'desc' => '会议场景'],
        ['id' => 8, 'photo' => 'album8.jpg', 'desc' => '雷锋纪念馆参观'],
        ['id' => 9, 'photo' => 'album9.jpg', 'desc' => '教导杯篮球赛'],
        ['id' => 10, 'photo' => 'album10.jpg', 'desc' => '体能训练'],
        ['id' => 11, 'photo' => 'album11.jpg', 'desc' => '表彰大会'],
        ['id' => 12, 'photo' => 'album12.jpg', 'desc' => '国防教育活动'],
        ['id' => 13, 'photo' => 'album13.jpg', 'desc' => '校园执勤'],
        ['id' => 14, 'photo' => 'album14.jpg', 'desc' => '文艺活动'],
        ['id' => 15, 'photo' => 'album15.jpg', 'desc' => '骨干培训'],
        ['id' => 16, 'photo' => 'album16.jpg', 'desc' => '内务整理'],
        ['id' => 17, 'photo' => 'album17.jpg', 'desc' => '新队员入队'],
        ['id' => 18, 'photo' => 'album18.jpg', 'desc' => '野外露营'],
        ['id' => 19, 'photo' => 'album19.jpg', 'desc' => '战术训练'],
        ['id' => 20, 'photo' => 'album20.jpg', 'desc' => '年度总结']
    ],
    'currentCadres' => [
        ['id' => 1, 'dept' => '常务委员会', 'name' => '何俊杰', 'post' => '大队长', 'desc' => '负责大队全面工作', 'photo' => 'member1.jpg'],
        ['id' => 2, 'dept' => '常务委员会', 'name' => '张钰', 'post' => '教导员', 'desc' => '负责思想政治工作', 'photo' => 'member2.jpg'],
        ['id' => 3, 'dept' => '常务委员会', 'name' => '陈启蕊', 'post' => '骨干中队长', 'desc' => '负责骨干队伍建设', 'photo' => 'member3.jpg'],
        ['id' => 4, 'dept' => '常务委员会', 'name' => '郑佳旺', 'post' => '副大队长', 'desc' => '协助大队长工作', 'photo' => 'member4.jpg'],
        ['id' => 5, 'dept' => '综合办公室', 'name' => '欧家', 'post' => '办公室主任', 'desc' => '负责办公室日常工作', 'photo' => 'office1.jpg'],
        ['id' => 6, 'dept' => '综合办公室', 'name' => '潘妍妍', 'post' => '办公室副主任', 'desc' => '协助办公室主任工作', 'photo' => 'office2.jpg'],
        ['id' => 7, 'dept' => '宣传部', 'name' => '艾彦廷', 'post' => '宣传部长', 'desc' => '负责宣传工作', 'photo' => 'xuanchuan1.jpg'],
        ['id' => 8, 'dept' => '宣传部', 'name' => '廖敏婕', 'post' => '宣传副部长', 'desc' => '协助宣传工作', 'photo' => 'xuanchuan2.jpg'],
        ['id' => 9, 'dept' => '拓展部', 'name' => '黄一倩', 'post' => '拓展部长', 'desc' => '负责拓展活动', 'photo' => 'tuozhan1.jpg'],
        ['id' => 10, 'dept' => '拓展部', 'name' => '聂玲飞', 'post' => '拓展副部长', 'desc' => '协助拓展活动', 'photo' => 'tuozhan2.jpg'],
        ['id' => 11, 'dept' => '后勤部', 'name' => '钟曼怡', 'post' => '后勤部长', 'desc' => '负责后勤保障', 'photo' => 'houqin1.jpg'],
        ['id' => 12, 'dept' => '后勤部', 'name' => '钱瑞鑫', 'post' => '后勤副部长', 'desc' => '协助后勤工作', 'photo' => 'houqin2.jpg'],
        ['id' => 13, 'dept' => '纪检部', 'name' => '江珧', 'post' => '纪检部长', 'desc' => '负责纪律检查', 'photo' => 'jicheng1.jpg'],
        ['id' => 14, 'dept' => '纪检部', 'name' => '桂祺欣', 'post' => '纪检副部长', 'desc' => '协助纪检工作', 'photo' => 'jicheng2.jpg'],
        ['id' => 15, 'dept' => '各中队', 'name' => '刘仕鹏', 'post' => '中队长', 'desc' => '负责烽火一中队', 'photo' => 'captain1.jpg'],
        ['id' => 16, 'dept' => '各中队', 'name' => '刘姿', 'post' => '中队长', 'desc' => '负责雷霆二中队', 'photo' => 'captain2.jpg'],
        ['id' => 17, 'dept' => '各中队', 'name' => '单荣祺', 'post' => '中队长', 'desc' => '负责雪狼三中队', 'photo' => 'captain3.jpg'],
        ['id' => 18, 'dept' => '各中队', 'name' => '李涛', 'post' => '中队长', 'desc' => '负责虎贲四中队', 'photo' => 'captain4.jpg'],
        ['id' => 19, 'dept' => '各中队', 'name' => '江涵', 'post' => '中队长', 'desc' => '负责苍龙五中队', 'photo' => 'captain5.jpg'],
        ['id' => 20, 'dept' => '各中队', 'name' => '符森', 'post' => '中队长', 'desc' => '负责火麒六中队', 'photo' => 'captain6.jpg']
    ],
    'formerCadres' => [
        ['id' => 1, 'term' => '第十四届', 'name' => '全德君', 'post' => '大队长', 'tenure' => '2024-2025', 'photo' => 'history_01.jpg'],
        ['id' => 2, 'term' => '第十四届', 'name' => '廖奕琪', 'post' => '教导员', 'tenure' => '2024-2025', 'photo' => 'history_02.jpg'],
        ['id' => 3, 'term' => '第十三届', 'name' => '尹尚琦', 'post' => '大队长', 'tenure' => '2023-2024', 'photo' => 'history_03.jpg'],
        ['id' => 4, 'term' => '第十三届', 'name' => '张玉洁', 'post' => '教导员', 'tenure' => '2023-2024', 'photo' => 'history_04.jpg'],
        ['id' => 5, 'term' => '第十二届', 'name' => '李博诚', 'post' => '大队长', 'tenure' => '2022-2023', 'photo' => 'history_05.jpg'],
        ['id' => 6, 'term' => '第十二届', 'name' => '王硕', 'post' => '教导员', 'tenure' => '2022-2023', 'photo' => 'history_06.jpg'],
        ['id' => 7, 'term' => '第十一届', 'name' => '蒋斌', 'post' => '大队长', 'tenure' => '2021-2022', 'photo' => 'history_07.jpg'],
        ['id' => 8, 'term' => '第十一届', 'name' => '刘明璇', 'post' => '教导员', 'tenure' => '2021-2022', 'photo' => 'history_08.jpg'],
        ['id' => 9, 'term' => '第十届', 'name' => '汪小宁', 'post' => '大队长', 'tenure' => '2020-2021', 'photo' => 'history_09.jpg'],
        ['id' => 10, 'term' => '第十届', 'name' => '陈文瑾', 'post' => '教导员', 'tenure' => '2020-2021', 'photo' => 'history_10.jpg']
    ],
    'teamHonors' => [
        ['id' => 1, 'title' => '新生军训特别贡献奖', 'photo' => 'honor_team_1.jpg', 'content' => '年度评优表彰'],
        ['id' => 2, 'title' => '高校军事技能竞赛优秀奖', 'photo' => 'honor_team_2.jpg', 'content' => '市级竞赛获奖'],
        ['id' => 3, 'title' => '优秀学生组织', 'photo' => 'honor_team_3.jpg', 'content' => '校级荣誉称号'],
        ['id' => 4, 'title' => '国防教育先进集体', 'photo' => 'honor_team_4.jpg', 'content' => '国防教育表彰'],
        ['id' => 5, 'title' => '射击比赛团体第七名', 'photo' => 'honor_team_5.jpg', 'content' => '省级竞赛奖项'],
        ['id' => 6, 'title' => '四会教练员集训优秀团队', 'photo' => 'honor_team_6.jpg', 'content' => '省级集训荣誉']
    ],
    'personalHonors' => [
        ['id' => 1, 'name' => '何俊杰', 'photo' => 'personal_honor1.jpg', 'honor' => '优秀学生干部', 'dept' => '常务委员会'],
        ['id' => 2, 'name' => '张钰', 'photo' => 'personal_honor2.jpg', 'honor' => '优秀共青团员', 'dept' => '常务委员会'],
        ['id' => 3, 'name' => '陈启蕊', 'photo' => 'personal_honor3.jpg', 'honor' => '训练标兵', 'dept' => '骨干中队'],
        ['id' => 4, 'name' => '艾彦廷', 'photo' => 'personal_honor4.jpg', 'honor' => '宣传工作先进个人', 'dept' => '宣传部'],
        ['id' => 5, 'name' => '黄一倩', 'photo' => 'personal_honor5.jpg', 'honor' => '活动组织先进个人', 'dept' => '拓展部']
    ],
    'excellentMembers' => [
        ['name' => '姓名1', 'post' => 'XX中队', 'photo' => 'excellent_1.jpg'],
        ['name' => '姓名2', 'post' => 'XX中队', 'photo' => 'excellent_2.jpg'],
        ['name' => '姓名3', 'post' => 'XX中队', 'photo' => 'excellent_3.jpg'],
        ['name' => '姓名4', 'post' => 'XX中队', 'photo' => 'excellent_4.jpg'],
        ['name' => '姓名5', 'post' => 'XX中队', 'photo' => 'excellent_5.jpg'],
        ['name' => '姓名6', 'post' => 'XX中队', 'photo' => 'excellent_6.jpg'],
        ['name' => '姓名7', 'post' => 'XX中队', 'photo' => 'excellent_7.jpg'],
        ['name' => '姓名8', 'post' => 'XX中队', 'photo' => 'excellent_8.jpg'],
        ['name' => '姓名9', 'post' => 'XX中队', 'photo' => 'excellent_9.jpg'],
        ['name' => '姓名10', 'post' => 'XX中队', 'photo' => 'excellent_10.jpg'],
        ['name' => '姓名11', 'post' => 'XX中队', 'photo' => 'excellent_11.jpg'],
        ['name' => '姓名12', 'post' => 'XX中队', 'photo' => 'excellent_12.jpg']
    ],
    'coaches' => [
        ['name' => '姓名1', 'post' => '教练员', 'photo' => 'coach_1.jpg'],
        ['name' => '姓名2', 'post' => '教练员', 'photo' => 'coach_2.jpg'],
        ['name' => '姓名3', 'post' => '教练员', 'photo' => 'coach_3.jpg'],
        ['name' => '姓名4', 'post' => '教练员', 'photo' => 'coach_4.jpg']
    ],
    'joinRecruit' => '湖南涉外经济学院学生志愿教导大队是一支军事化队伍的校级组织，接受学校学生工作处（人民武装部）的领导，新训期间由军训领导小组直接领导管理。日常由学生工作处（人民武装部）直接领导管理。

教导大队设有七个中队，分别为凌云骨干队；烽火一中队；雷霆二中队；雪狼三中队；虎贲四中队；苍龙五中队；火麒六中队。',
    'joinCondition' => "✨ 湖南涉外经济学院在籍大一大二学生，不限专业、不限性别，热爱国防\n✨ 向往军旅风采，认同大队宗旨与纪律\n✨ 态度端正、吃苦耐劳，有责任心、有执行力\n✨ 不惧挑战、愿意突破自我，想提升自律与综合能力\n✨ 无特殊纪律限制，不限基础、不限特长\n✨ 学习成绩良好，无挂科记录（优先考虑）\n✨ 退伍军人、体育特长（优先考虑）\n✨ 政治立场坚定，纪律意识强（优先考虑）",
    'joinProcess' => "1. 线上报名：通过表单或微信扫码填写\n2. 初试：面试+体能测试\n3. 复试：集中高强度训练\n4. 公示录取：公布最终拟录取名册",
    'joinGroup' => '招新咨询群：后续进行告知',
    'joinOffice' => '办公地点：南四栋1楼102室',
    'joinTime' => '咨询时间：周一至周五 17:00-19:00',
    'joinImages' => ['join_qrcode1.jpg', 'join_qrcode2.jpg', 'join_qrcode3.jpg', 'join_qrcode4.jpg']
];

// ==================== 工具函数 ====================

function getDataFilePath($key) {
    return DATA_DIR . '/' . $key . '.json';
}

function loadData($key) {
    $filePath = getDataFilePath($key);
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        $data = json_decode($content, true);
        if ($data !== null) return $data;
    }
    return isset($DEFAULT_DATA[$key]) ? $DEFAULT_DATA[$key] : null;
}

function saveData($key, $data) {
    $filePath = getDataFilePath($key);
    $dir = dirname($filePath);

    // 确保目录存在
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    // 原子写入：先写临时文件，再重命名（防止并发写入导致数据损坏）
    $tempFile = $dir . '/.' . $key . '_' . uniqid() . '.tmp';
    $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    $result = file_put_contents($tempFile, $jsonData, LOCK_EX);
    if ($result === false) {
        // 清理临时文件
        @unlink($tempFile);
        return false;
    }

    // 原子重命名
    if (@rename($tempFile, $filePath)) {
        return true;
    } else {
        // rename失败（可能跨文件系统），回退到直接写入
        @unlink($tempFile);
        $result = file_put_contents($filePath, $jsonData, LOCK_EX);
        return $result !== false;
    }
}

function formatFileSize($bytes) {
    if ($bytes < 1024) return $bytes . 'B';
    if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . 'KB';
    return round($bytes / (1024 * 1024), 1) . 'MB';
}

// 创建单个数据备份
function createSingleBackup($key) {
    $filePath = getDataFilePath($key);
    if (!file_exists($filePath)) return null;

    $timestamp = time() . sprintf('%03d', rand(0, 999));
    $filename = 'backup_' . $key . '_' . $timestamp . '.json';
    $backupPath = BACKUP_DIR . '/' . $filename;

    copy($filePath, $backupPath);
    cleanupOldBackups();
    return $filename;
}

// 创建全量备份
function createFullBackup() {
    $timestamp = time() . sprintf('%03d', rand(0, 999));
    $filename = 'backup_full_' . $timestamp . '.json';
    $backupPath = BACKUP_DIR . '/' . $filename;

    $allData = [];
    foreach ($DEFAULT_DATA as $key => $val) {
        $allData[$key] = loadData($key);
    }

    file_put_contents($backupPath, json_encode($allData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    cleanupOldBackups();
    return $filename;
}

// 清理旧备份
function cleanupOldBackups() {
    if (!is_dir(BACKUP_DIR)) return;

    $files = glob(BACKUP_DIR . '/backup_*.json');
    if (!$files) return;

    $fullBackups = [];
    $singleBackups = [];

    foreach ($files as $f) {
        $basename = basename($f);
        if (strpos($basename, 'backup_full_') === 0) {
            $fullBackups[$f] = filemtime($f);
        } else {
            $singleBackups[$f] = filemtime($f);
        }
    }

    // 排序并清理超限的全量备份
    arsort($fullBackups);
    if (count($fullBackups) > MAX_FULL_BACKUPS) {
        $toDelete = array_slice($fullBackups, MAX_FULL_BACKUPS, null, true);
        foreach ($toDelete as $f => $t) @unlink($f);
    }

    // 排序并清理超限的单数据备份
    arsort($singleBackups);
    if (count($singleBackups) > MAX_SINGLE_BACKUPS) {
        $toDelete = array_slice($singleBackups, MAX_SINGLE_BACKUPS, null, true);
        foreach ($toDelete as $f => $t) @unlink($f);
    }
}

// 获取备份列表
function getBackupList() {
    $files = glob(BACKUP_DIR . '/backup_*.json');
    $result = [];

    foreach ($files as $f) {
        $basename = basename($f);
        $stat = stat($f);

        if (strpos($basename, 'backup_full_') === 0) {
            $type = 'full';
        } else {
            $type = preg_replace('/^backup_(.+?)_\d+\.json$/', '$1', $basename);
            if ($type === $basename) $type = 'unknown';
        }

        $result[] = [
            'filename' => $basename,
            'time' => date('Y-m-d H:i:s', $stat['mtime']),
            'size' => formatFileSize($stat['size']),
            'type' => $type
        ];
    }

    // 按文件名倒序排列（时间戳越大越新）
    usort($result, function($a, $b) {
        return strcmp($b['filename'], $a['filename']);
    });

    return $result;
}

// 从备份恢复
function restoreFromBackup($filename) {
    // 安全检查
    if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
        return ['success' => false, 'message' => '非法的文件名'];
    }

    $backupPath = BACKUP_DIR . '/' . $filename;
    if (!file_exists($backupPath)) {
        return ['success' => false, 'message' => '备份文件不存在: ' . $filename];
    }

    $data = json_decode(file_get_contents($backupPath), true);
    if (!$data) {
        return ['success' => false, 'message' => '备份文件格式错误'];
    }

    // 恢复前先创建当前数据的备份
    createFullBackup();

    foreach ($data as $key => $value) {
        saveData($key, $value);
    }

    // 同步新闻数据到前端
    if (isset($data['newsData'])) {
        @file_put_contents(FRONTEND_NEWS_PATH, json_encode($data['newsData'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    return ['success' => true, 'message' => '从备份 ' . $filename . ' 恢复成功', 'restoredKeys' => array_keys($data)];
}

// 删除备份
function deleteBackupFile($filename) {
    if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
        return ['success' => false, 'message' => '非法的文件名'];
    }

    $backupPath = BACKUP_DIR . '/' . $filename;
    if (!file_exists($backupPath)) {
        return ['success' => false, 'message' => '备份文件不存在'];
    }

    @unlink($backupPath);
    return ['success' => true, 'message' => '备份文件 ' . $filename . ' 已删除'];
}

// 获取操作人信息（优先请求头，其次 session）
function getApiOperator() {
    $headers = [];
    foreach (['X-Operator-Name', 'X-Operator-OpenId', 'X-Operator-Method'] as $h) {
        $k = 'HTTP_' . strtoupper(str_replace('-', '_', $h));
        $headers[$h] = isset($_SERVER[$k]) ? trim($_SERVER[$k]) : '';
    }
    $name = $headers['X-Operator-Name'];
    $openId = $headers['X-Operator-OpenId'];

    if (empty($name) && session_status() === PHP_SESSION_NONE) @session_start();
    if (empty($name) && !empty($_SESSION['feishu_user']['name'])) {
        $name = $_SESSION['feishu_user']['name'];
        $openId = $_SESSION['feishu_user']['open_id'] ?? '';
    }
    return [empty($name) ? '未知' : $name, $openId];
}

// 获取请求参数
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];

// 解析路径
$path = parse_url($requestUri, PHP_URL_PATH);
$path = str_replace('/api/', '', $path);
$path = rtrim($path, '/');

// 获取请求体（POST）
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true) ?: [];

// ==================== 路由分发 ====================

// 健康检查
if ($path === 'health') {
    echo json_encode(['success' => true, 'message' => '服务运行正常', 'timestamp' => date('c')]);
    exit;
}

// 根路由
if ($path === '' || $path === '/') {
    echo json_encode([
        'success' => true,
        'message' => '学生志愿教导大队管理后台 API 服务运行正常（PHP版）',
        'timestamp' => date('c')
    ]);
    exit;
}

// GET /api/data - 获取所有数据
if ($path === 'data' && $method === 'GET') {
    $allData = [];
    foreach ($DEFAULT_DATA as $key => $val) {
        $allData[$key] = loadData($key);
    }
    echo json_encode(['success' => true, 'data' => $allData]);
    exit;
}

// GET /api/data/:key - 获取单个数据
if (preg_match('#^data/(.+)$#', $path, $matches) && $method === 'GET') {
    $key = $matches[1];
    $data = loadData($key);
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

// POST /api/data/:key - 保存数据
if (preg_match('#^data/(.+)$#', $path, $matches) && $method === 'POST') {
    $key = $matches[1];

    // 保存前自动创建备份
    $backupFilename = createSingleBackup($key);

    // 新闻数据：记录变更前的数据用于飞书通知
    $oldNewsData = ($key === 'newsData') ? loadData('newsData') : [];

    $success = saveData($key, $input);

    // 如果是新闻数据，自动同步到前端 news_data.json
    if ($key === 'newsData' && $success) {
        @file_put_contents(FRONTEND_NEWS_PATH, json_encode($input, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // 发送飞书群机器人新闻变更通知（失败不影响保存结果）
        try {
            list($operatorName, $operatorOpenId) = getApiOperator();
            notifyNewsChanged($operatorName, $operatorOpenId, $oldNewsData, $input);
        } catch (Throwable $e) {
            error_log('[新闻变更通知] 发送失败: ' . $e->getMessage());
        }
    }

    $message = $success ? '保存成功' : '保存失败';
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

// POST /api/backup - 创建全量备份
if ($path === 'backup' && $method === 'POST') {
    $backupFilename = createFullBackup();
    if ($backupFilename) {
        $backups = getBackupList();
        echo json_encode([
            'success' => true,
            'message' => '全量备份成功: ' . $backupFilename,
            'backups' => $backups
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '全量备份失败']);
    }
    exit;
}

// GET /api/backup - 获取备份列表
if ($path === 'backup' && $method === 'GET') {
    $backups = getBackupList();
    echo json_encode([
        'success' => true,
        'backups' => $backups,
        'count' => count($backups)
    ]);
    exit;
}

// POST /api/backup/restore - 从备份恢复
if ($path === 'backup/restore' && $method === 'POST') {
    $filename = isset($input['filename']) ? $input['filename'] : '';
    if (!$filename) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '请指定要恢复的备份文件名']);
        exit;
    }
    $result = restoreFromBackup($filename);
    echo json_encode($result);
    exit;
}

// DELETE /api/backup/:filename - 删除备份
if (preg_match('#^backup/(.+)$#', $path, $matches) && $method === 'DELETE') {
    $filename = $matches[1];
    $result = deleteBackupFile($filename);
    if (!$result['success']) {
        http_response_code(404);
    }
    echo json_encode($result);
    exit;
}

// POST /api/upload - 上传文件（支持批量上传）
if ($path === 'upload' && $method === 'POST') {
    // 批量上传模式
    if (isset($input['images']) && is_array($input['images'])) {
        $results = [];
        foreach ($input['images'] as $idx => $imgData) {
            if (!isset($imgData['content']) || empty($imgData['content'])) {
                $results[$idx] = ['success' => false, 'message' => '图片数据为空'];
                continue;
            }
            
            $result = saveUploadedImage(
                isset($imgData['filename']) ? $imgData['filename'] : '',
                $imgData['content']
            );
            $results[$idx] = $result;
        }
        
        // 统计结果
        $successCount = count(array_filter($results, function($r) { return $r['success']; }));
        echo json_encode([
            'success' => $successCount > 0,
            'message' => "成功上传 {$successCount}/" . count($results) . " 张图片",
            'results' => $results
        ]);
        exit;
    }
    
    // 单张上传模式（兼容旧接口）
    $filename = isset($input['filename']) ? $input['filename'] : '';
    $content = isset($input['content']) ? $input['content'] : '';

    $result = saveUploadedImage($filename, $content);
    
    if (!$result['success']) {
        http_response_code(400);
    }
    echo json_encode($result);
    exit;
}

/**
 * 保存单张上传的图片
 */
function saveUploadedImage($filename, $content) {
    // 上传大小限制：单张最大10MB
    define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);

    if (empty($content)) {
        return ['success' => false, 'message' => '图片数据为空', 'url' => ''];
    }

    // 允许的图片类型白名单
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

    // 从 base64 数据中提取文件类型
    $fileType = 'jpg';
    if (preg_match('/^data:image\/(\w+);/', $content, $m)) {
        $fileType = strtolower($m[1]);
        if ($fileType === 'jpeg') $fileType = 'jpg';
    }

    // 类型白名单校验
    if (!in_array($fileType, $allowedTypes)) {
        return ['success' => false, 'message' => '不支持的文件类型: ' . $fileType, 'url' => ''];
    }

    // 自动生成文件名（如果未提供）
    if (empty($filename)) {
        $filename = 'uploads/news_' . date('YmdHis') . '_' . sprintf('%03d', rand(0, 999)) . '.' . $fileType;
    }

    // 安全检查路径：使用basename剥离目录，多层防护
    $safeFilename = basename($filename);
    // 二次验证：只允许字母、数字、下划线、连字符、点号
    if (!preg_match('/^[\w\-\.]+$/', $safeFilename) || preg_match('/\.\./', $filename)) {
        return ['success' => false, 'message' => '非法的文件名', 'url' => ''];
    }
    // 确保扩展名在白名单中
    $ext = strtolower(pathinfo($safeFilename, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedTypes)) {
        $safeFilename = preg_replace('/\.[^.]+$/', '', $safeFilename) . '.' . $fileType;
    }

    $filePath = __DIR__ . '/../' . $safeFilename;
    $base64Data = preg_replace('/^data:image\/\w+;base64,/', '', $content);
    $decoded = base64_decode($base64Data);

    if ($decoded === false) {
        return ['success' => false, 'message' => 'Base64解码失败', 'url' => ''];
    }

    // 文件大小限制检查
    if (strlen($decoded) > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'message' => '文件过大（最大' . (MAX_UPLOAD_SIZE / 1024 / 1024) . 'MB）', 'url' => ''];
    }

    $dir = dirname($filePath);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $writeResult = file_put_contents($filePath, $decoded, LOCK_EX);

    if ($writeResult !== false) {
        // 返回可访问的URL
        $url = '/' . $safeFilename;
        return [
            'success' => true,
            'message' => '上传成功',
            'url' => $url,
            'filename' => $safeFilename,
            'size' => strlen($decoded)
        ];
    } else {
        return ['success' => false, 'message' => '写入文件失败', 'url' => ''];
    }
}

// 未匹配的路由
http_response_code(404);
echo json_encode(['success' => false, 'message' => '接口不存在: /api/' . $path]);
