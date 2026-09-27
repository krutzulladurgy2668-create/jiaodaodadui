const express = require('express');
const cors = require('cors');
const path = require('path');
const fs = require('fs');
const app = express();
const PORT = 3000;

app.use(cors({
    origin: '*',  // 生产环境建议设为具体域名
    methods: ['GET', 'POST', 'PUT', 'DELETE'],
    allowedHeaders: ['Content-Type'],
    maxAge: 86400
}));
app.use(express.json({ limit: '50mb' }));
app.use(express.urlencoded({ extended: true, limit: '50mb' }));
app.use(express.static(path.join(__dirname, '..')));

// 添加请求日志
app.use((req, res, next) => {
    console.log(`[${new Date().toLocaleString()}] ${req.method} ${req.url}`);
    next();
});

const DATA_DIR = path.join(__dirname, 'data');
const BACKUP_DIR = path.join(DATA_DIR, 'backups');
if (!fs.existsSync(DATA_DIR)) {
    fs.mkdirSync(DATA_DIR, { recursive: true });
}
if (!fs.existsSync(BACKUP_DIR)) {
    fs.mkdirSync(BACKUP_DIR, { recursive: true });
    console.log(`创建备份目录: ${BACKUP_DIR}`);
}

// 初始化所有默认数据到文件
function initDefaultData() {
    console.log('正在初始化默认数据...');
    for (const key in DEFAULT_DATA) {
        const filePath = getDataFilePath(key);
        if (!fs.existsSync(filePath)) {
            console.log(`  初始化 ${key}...`);
            saveData(key, DEFAULT_DATA[key]);
        }
    }
    console.log('✅ 默认数据初始化完成！');
}

const DEFAULT_DATA = {
    newsData: [],
    currentLeaders: [
        { id: 1, name: '徐京都', post: '校长助理、学生工作处处长', desc: '徐京都，男，汉族，中共党员，现任湖南涉外经济学院校长助理、主管学生工作部（处）。', photo: 'leader1.jpg' },
        { id: 2, name: '夏建平', post: '武装部副部长、教导大队指导老师', desc: '夏建平，男，汉族，中共党员，现任湖南涉外经济学院人民武装部副部长、军训领导小组办公室副主任、学生志愿教导大队指导老师。', photo: 'leader2.jpg' }
    ],
    orgStructure: {
        departments: [
            { name: '常务委员会', members: ['何俊杰', '张钰', '陈启蕊', '郑佳旺'] },
            { name: '综合办公室', members: ['欧家', '潘妍妍'] },
            { name: '宣传部', members: ['艾彦廷', '廖敏婕'] },
            { name: '拓展部', members: ['黄一倩', '聂玲飞'] },
            { name: '后勤部', members: ['钟曼怡', '钱瑞鑫'] },
            { name: '纪检部', members: ['江珧', '桂祺欣'] }
        ],
        squadrons: [
            { name: '凌云骨干队', leader: '陈启蕊' },
            { name: '烽火一中队', leader: '刘仕鹏' },
            { name: '雷霆二中队', leader: '刘姿' },
            { name: '雪狼三中队', leader: '单荣祺' },
            { name: '虎贲四中队', leader: '李涛' },
            { name: '苍龙五中队', leader: '江涵' },
            { name: '火麒六中队', leader: '符森' }
        ]
    },
    homeOverview: '湖南涉外经济学院学生志愿教导大队是一支半军事化队伍，接受学校学生工作处（人民武装部）的领导，新生军训期间由军训领导小组直接领导管理。日常由学生工作处（人民武装部）直接领导管理，教导大队未经学校批准，不得随意出动，在执行任务过程中，必须组成分队或小组并服从指定带队人指挥，教导大队队员集体执行任务和训练时必须统一着军装、佩戴校徽。严禁非任务或训练期间穿着军服。\n\n教导大队成立于2011年，是一支由武装部领导的军事化队伍。大队以"明理严军、自强不息"为队训，实行严格的半军事化管理，坚持纪律严明、训练刻苦、作风扎实、甘于奉献，是校园精神文明建设与国防教育工作的重要骨干力量，多次荣获省、校级表彰与高度认可。',
    aboutText1: '湖南涉外经济学院学生志愿教导大队是一支半军事化队伍，接受学校学生工作处（人民武装部）的领导，新生军训期间由军训领导小组直接领导管理。日常由学生工作处（人民武装部）直接领导管理。',
    aboutText2: '教导大队成立于2011年，是一支由武装部领导的军事化队伍。教导大队设有六个部门，分别为教导大队常务委员会、综合办公室、宣传部、拓展部、后勤部、纪检部。教导大队设有七个中队，分别为凌云骨干队；烽火一中队；雷霆二中队；雪狼三中队；虎贲四中队；苍龙五中队；火麒六中队。',
    aboutText3: '学生志愿教导大队的主要职能是：承担学校新生军训及国防安全教育，执行校园文明教育与劝导，协助执行校园秩序巡逻、重大活动值勤，每周举行校园升旗仪式。',
    aboutText4: '学生志愿教导大队的队员是由在校大一大二学生自主报名、教官推荐、经大队严格选拔，高强度集中训练之后组成。实行军事化管理，统一着装。教导大队严格实行考核淘汰制，以维护队伍的组织性和纪律性。',
    aboutMotto: '明理严军 · 自强不息',
    sliderImages: ['slide1.jpg', 'slide2.jpg', 'slide3.jpg', 'slide4.jpg', 'slide5.jpg'],
    aboutImages: ['pic1.jpg', 'pic2.jpg', 'pic3.jpg'],
    albumImages: [
        { id: 1, photo: 'album1.jpg', desc: '升旗仪式' },
        { id: 2, photo: 'album2.jpg', desc: '野外拉练' },
        { id: 3, photo: 'album3.jpg', desc: '授衔仪式' },
        { id: 4, photo: 'album4.jpg', desc: '拓展训练' },
        { id: 5, photo: 'album5.jpg', desc: '军事训练' },
        { id: 6, photo: 'album6.jpg', desc: '队员合影' },
        { id: 7, photo: 'album7.jpg', desc: '会议场景' },
        { id: 8, photo: 'album8.jpg', desc: '雷锋纪念馆参观' },
        { id: 9, photo: 'album9.jpg', desc: '教导杯篮球赛' },
        { id: 10, photo: 'album10.jpg', desc: '体能训练' },
        { id: 11, photo: 'album11.jpg', desc: '表彰大会' },
        { id: 12, photo: 'album12.jpg', desc: '国防教育活动' },
        { id: 13, photo: 'album13.jpg', desc: '校园执勤' },
        { id: 14, photo: 'album14.jpg', desc: '文艺活动' },
        { id: 15, photo: 'album15.jpg', desc: '骨干培训' },
        { id: 16, photo: 'album16.jpg', desc: '内务整理' },
        { id: 17, photo: 'album17.jpg', desc: '新队员入队' },
        { id: 18, photo: 'album18.jpg', desc: '野外露营' },
        { id: 19, photo: 'album19.jpg', desc: '战术训练' },
        { id: 20, photo: 'album20.jpg', desc: '年度总结' }
    ],
    currentCadres: [
        { id: 1, dept: '常务委员会', name: '何俊杰', post: '大队长', desc: '负责大队全面工作', photo: 'member1.jpg' },
        { id: 2, dept: '常务委员会', name: '张钰', post: '教导员', desc: '负责思想政治工作', photo: 'member2.jpg' },
        { id: 3, dept: '常务委员会', name: '陈启蕊', post: '骨干中队长', desc: '负责骨干队伍建设', photo: 'member3.jpg' },
        { id: 4, dept: '常务委员会', name: '郑佳旺', post: '副大队长', desc: '协助大队长工作', photo: 'member4.jpg' },
        { id: 5, dept: '综合办公室', name: '欧家', post: '办公室主任', desc: '负责办公室日常工作', photo: 'office1.jpg' },
        { id: 6, dept: '综合办公室', name: '潘妍妍', post: '办公室副主任', desc: '协助办公室主任工作', photo: 'office2.jpg' },
        { id: 7, dept: '宣传部', name: '艾彦廷', post: '宣传部长', desc: '负责宣传工作', photo: 'xuanchuan1.jpg' },
        { id: 8, dept: '宣传部', name: '廖敏婕', post: '宣传副部长', desc: '协助宣传工作', photo: 'xuanchuan2.jpg' },
        { id: 9, dept: '拓展部', name: '黄一倩', post: '拓展部长', desc: '负责拓展活动', photo: 'tuozhan1.jpg' },
        { id: 10, dept: '拓展部', name: '聂玲飞', post: '拓展副部长', desc: '协助拓展活动', photo: 'tuozhan2.jpg' },
        { id: 11, dept: '后勤部', name: '钟曼怡', post: '后勤部长', desc: '负责后勤保障', photo: 'houqin1.jpg' },
        { id: 12, dept: '后勤部', name: '钱瑞鑫', post: '后勤副部长', desc: '协助后勤工作', photo: 'houqin2.jpg' },
        { id: 13, dept: '纪检部', name: '江珧', post: '纪检部长', desc: '负责纪律检查', photo: 'jicheng1.jpg' },
        { id: 14, dept: '纪检部', name: '桂祺欣', post: '纪检副部长', desc: '协助纪检工作', photo: 'jicheng2.jpg' },
        { id: 15, dept: '各中队', name: '刘仕鹏', post: '中队长', desc: '负责烽火一中队', photo: 'captain1.jpg' },
        { id: 16, dept: '各中队', name: '刘姿', post: '中队长', desc: '负责雷霆二中队', photo: 'captain2.jpg' },
        { id: 17, dept: '各中队', name: '单荣祺', post: '中队长', desc: '负责雪狼三中队', photo: 'captain3.jpg' },
        { id: 18, dept: '各中队', name: '李涛', post: '中队长', desc: '负责虎贲四中队', photo: 'captain4.jpg' },
        { id: 19, dept: '各中队', name: '江涵', post: '中队长', desc: '负责苍龙五中队', photo: 'captain5.jpg' },
        { id: 20, dept: '各中队', name: '符森', post: '中队长', desc: '负责火麒六中队', photo: 'captain6.jpg' }
    ],
    formerCadres: [
        { id: 1, term: '第十四届', name: '全德君', post: '大队长', tenure: '2024-2025', photo: 'history_01.jpg' },
        { id: 2, term: '第十四届', name: '廖奕琪', post: '教导员', tenure: '2024-2025', photo: 'history_02.jpg' },
        { id: 3, term: '第十三届', name: '尹尚琦', post: '大队长', tenure: '2023-2024', photo: 'history_03.jpg' },
        { id: 4, term: '第十三届', name: '张玉洁', post: '教导员', tenure: '2023-2024', photo: 'history_04.jpg' },
        { id: 5, term: '第十二届', name: '李博诚', post: '大队长', tenure: '2022-2023', photo: 'history_05.jpg' },
        { id: 6, term: '第十二届', name: '王硕', post: '教导员', tenure: '2022-2023', photo: 'history_06.jpg' },
        { id: 7, term: '第十一届', name: '蒋斌', post: '大队长', tenure: '2021-2022', photo: 'history_07.jpg' },
        { id: 8, term: '第十一届', name: '刘明璇', post: '教导员', tenure: '2021-2022', photo: 'history_08.jpg' },
        { id: 9, term: '第十届', name: '汪小宁', post: '大队长', tenure: '2020-2021', photo: 'history_09.jpg' },
        { id: 10, term: '第十届', name: '陈文瑾', post: '教导员', tenure: '2020-2021', photo: 'history_10.jpg' }
    ],
    teamHonors: [
        { id: 1, title: '新生军训特别贡献奖', photo: 'honor_team_1.jpg', content: '年度评优表彰' },
        { id: 2, title: '高校军事技能竞赛优秀奖', photo: 'honor_team_2.jpg', content: '市级竞赛获奖' },
        { id: 3, title: '优秀学生组织', photo: 'honor_team_3.jpg', content: '校级荣誉称号' },
        { id: 4, title: '国防教育先进集体', photo: 'honor_team_4.jpg', content: '国防教育表彰' },
        { id: 5, title: '射击比赛团体第七名', photo: 'honor_team_5.jpg', content: '省级竞赛奖项' },
        { id: 6, title: '四会教练员集训优秀团队', photo: 'honor_team_6.jpg', content: '省级集训荣誉' }
    ],
    personalHonors: [
        { id: 1, name: '何俊杰', photo: 'personal_honor1.jpg', honor: '优秀学生干部', dept: '常务委员会' },
        { id: 2, name: '张钰', photo: 'personal_honor2.jpg', honor: '优秀共青团员', dept: '常务委员会' },
        { id: 3, name: '陈启蕊', photo: 'personal_honor3.jpg', honor: '训练标兵', dept: '骨干中队' },
        { id: 4, name: '艾彦廷', photo: 'personal_honor4.jpg', honor: '宣传工作先进个人', dept: '宣传部' },
        { id: 5, name: '黄一倩', photo: 'personal_honor5.jpg', honor: '活动组织先进个人', dept: '拓展部' }
    ],
    excellentMembers: [
        { name: '姓名1', post: 'XX中队', photo: 'excellent_1.jpg' },
        { name: '姓名2', post: 'XX中队', photo: 'excellent_2.jpg' },
        { name: '姓名3', post: 'XX中队', photo: 'excellent_3.jpg' },
        { name: '姓名4', post: 'XX中队', photo: 'excellent_4.jpg' },
        { name: '姓名5', post: 'XX中队', photo: 'excellent_5.jpg' },
        { name: '姓名6', post: 'XX中队', photo: 'excellent_6.jpg' },
        { name: '姓名7', post: 'XX中队', photo: 'excellent_7.jpg' },
        { name: '姓名8', post: 'XX中队', photo: 'excellent_8.jpg' },
        { name: '姓名9', post: 'XX中队', photo: 'excellent_9.jpg' },
        { name: '姓名10', post: 'XX中队', photo: 'excellent_10.jpg' },
        { name: '姓名11', post: 'XX中队', photo: 'excellent_11.jpg' },
        { name: '姓名12', post: 'XX中队', photo: 'excellent_12.jpg' }
    ],
    coaches: [
        { name: '姓名1', post: '教练员', photo: 'coach_1.jpg' },
        { name: '姓名2', post: '教练员', photo: 'coach_2.jpg' },
        { name: '姓名3', post: '教练员', photo: 'coach_3.jpg' },
        { name: '姓名4', post: '教练员', photo: 'coach_4.jpg' }
    ],
    joinRecruit: '湖南涉外经济学院学生志愿教导大队是一支军事化队伍的校级组织，接受学校学生工作处（人民武装部）的领导，新训期间由军训领导小组直接领导管理。日常由学生工作处（人民武装部）直接领导管理。\n\n教导大队设有七个中队，分别为凌云骨干队；烽火一中队；雷霆二中队；雪狼三中队；虎贲四中队；苍龙五中队；火麒六中队。',
    joinCondition: '✨ 湖南涉外经济学院在籍大一大二学生，不限专业、不限性别，热爱国防\n✨ 向往军旅风采，认同大队宗旨与纪律\n✨ 态度端正、吃苦耐劳，有责任心、有执行力\n✨ 不惧挑战、愿意突破自我，想提升自律与综合能力\n✨ 无特殊纪律限制，不限基础、不限特长\n✨ 学习成绩良好，无挂科记录（优先考虑）\n✨ 退伍军人、体育特长（优先考虑）\n✨ 政治立场坚定，纪律意识强（优先考虑）',
    joinProcess: '1. 线上报名：通过表单或微信扫码填写\n2. 初试：面试+体能测试\n3. 复试：集中高强度训练\n4. 公示录取：公布最终拟录取名册',
    joinGroup: '招新咨询群：后续进行告知',
    joinOffice: '办公地点：南四栋1楼102室',
    joinTime: '咨询时间：周一至周五 17:00-19:00',
    joinImages: ['join_qrcode1.jpg', 'join_qrcode2.jpg', 'join_qrcode3.jpg', 'join_qrcode4.jpg']
};

function getDataFilePath(key) {
    return path.join(DATA_DIR, `${key}.json`);
}

function loadData(key) {
    const filePath = getDataFilePath(key);
    try {
        if (fs.existsSync(filePath)) {
            return JSON.parse(fs.readFileSync(filePath, 'utf8'));
        }
        return DEFAULT_DATA[key] || null;
    } catch (e) {
        console.error(`读取数据${key}失败:`, e);
        return DEFAULT_DATA[key] || null;
    }
}

function saveData(key, data) {
    const filePath = getDataFilePath(key);
    try {
        console.log(`正在保存 ${key} 到: ${filePath}`);

        // 确保目录存在
        const dir = path.dirname(filePath);
        if (!fs.existsSync(dir)) {
            fs.mkdirSync(dir, { recursive: true });
        }

        // 原子写入：先写临时文件，再重命名
        const tempFile = filePath + '.tmp_' + Date.now() + '_' + Math.random().toString(36).slice(2);
        fs.writeFileSync(tempFile, JSON.stringify(data, null, 2), 'utf8');

        // 原子重命名（同文件系统下是原子操作）
        try {
            fs.renameSync(tempFile, filePath);
        } catch (renameErr) {
            // 跨文件系统时rename可能失败，回退到直接写入
            console.warn('原子重命名失败，使用直接写入模式:', renameErr.message);
            fs.writeFileSync(filePath, JSON.stringify(data, null, 2), 'utf8');
            try { fs.unlinkSync(tempFile); } catch(e) {} // 清理临时文件
        }

        console.log(`保存 ${key} 成功!`);
        return true;
    } catch (e) {
        console.error(`保存数据${key}失败:`, e.message);
        console.error('错误详情:', e);
        return false;
    }
}

// ==================== 备份相关函数 ====================

const MAX_FULL_BACKUPS = 10;
const MAX_SINGLE_BACKUPS = 20;

// 格式化文件大小
function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + 'B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + 'KB';
    return (bytes / (1024 * 1024)).toFixed(1) + 'MB';
}

// 创建单个数据的备份
function createSingleBackup(key) {
    try {
        const filePath = getDataFilePath(key);
        if (!fs.existsSync(filePath)) {
            console.log(`[备份] 数据文件 ${key} 不存在，跳过备份`);
            return null;
        }
        const data = fs.readFileSync(filePath, 'utf8');
        const timestamp = Date.now();
        const filename = `backup_${key}_${timestamp}.json`;
        const backupPath = path.join(BACKUP_DIR, filename);
        fs.writeFileSync(backupPath, data, 'utf8');
        console.log(`[备份] 创建单数据备份成功: ${filename}`);
        cleanupOldBackups();
        return filename;
    } catch (e) {
        console.error(`[备份] 创建单数据备份失败 (${key}):`, e.message);
        return null;
    }
}

// 创建全量备份
function createFullBackup() {
    try {
        const timestamp = Date.now();
        const filename = `backup_full_${timestamp}.json`;
        const backupPath = path.join(BACKUP_DIR, filename);
        const allData = {};
        for (const key in DEFAULT_DATA) {
            allData[key] = loadData(key);
        }
        fs.writeFileSync(backupPath, JSON.stringify(allData, null, 2), 'utf8');
        console.log(`[备份] 创建全量备份成功: ${filename}`);
        cleanupOldBackups();
        return filename;
    } catch (e) {
        console.error(`[备份] 创建全量备份失败:`, e.message);
        return null;
    }
}

// 清理超过限制的旧备份
function cleanupOldBackups() {
    try {
        if (!fs.existsSync(BACKUP_DIR)) return;
        const files = fs.readdirSync(BACKUP_DIR).filter(f => f.endsWith('.json') && f.startsWith('backup_'));
        // 分离全量备份和单数据备份
        const fullBackups = files.filter(f => f.startsWith('backup_full_')).sort();
        const singleBackups = files.filter(f => !f.startsWith('backup_full_')).sort();
        // 清理超限的全量备份
        if (fullBackups.length > MAX_FULL_BACKUPS) {
            const toDelete = fullBackups.slice(0, fullBackups.length - MAX_FULL_BACKUPS);
            toDelete.forEach(f => {
                fs.unlinkSync(path.join(BACKUP_DIR, f));
                console.log(`[清理] 删除旧全量备份: ${f}`);
            });
        }
        // 清理超限的单数据备份
        if (singleBackups.length > MAX_SINGLE_BACKUPS) {
            const toDelete = singleBackups.slice(0, singleBackups.length - MAX_SINGLE_BACKUPS);
            toDelete.forEach(f => {
                fs.unlinkSync(path.join(BACKUP_DIR, f));
                console.log(`[清理] 删除旧单数据备份: ${f}`);
            });
        }
    } catch (e) {
        console.error(`[清理] 清理旧备份失败:`, e.message);
    }
}

// 获取所有备份列表（按时间倒序）
function getBackupList() {
    try {
        if (!fs.existsSync(BACKUP_DIR)) return [];
        const files = fs.readdirSync(BACKUP_DIR)
            .filter(f => f.endsWith('.json') && f.startsWith('backup_'))
            .map(f => {
                const filePath = path.join(BACKUP_DIR, f);
                const stat = fs.statSync(filePath);
                let type = 'unknown';
                if (f.startsWith('backup_full_')) {
                    type = 'full';
                } else {
                    const match = f.match(/^backup_(.+?)_\d+\.json$/);
                    type = match ? match[1] : 'unknown';
                }
                return {
                    filename: f,
                    time: new Date(stat.mtime).toLocaleString(),
                    size: formatFileSize(stat.size),
                    type: type
                };
            });
        // 按时间倒序排列（文件名中的时间戳越大越新）
        files.sort((a, b) => {
            const timeA = parseInt(a.filename.match(/\d+\.json$/)[0]);
            const timeB = parseInt(b.filename.match(/\d+\.json$/)[0]);
            return timeB - timeA;
        });
        return files;
    } catch (e) {
        console.error(`[备份] 获取备份列表失败:`, e.message);
        return [];
    }
}

// 从备份恢复数据
function restoreFromBackup(filename) {
    try {
        const backupPath = path.join(BACKUP_DIR, filename);
        if (!fs.existsSync(backupPath)) {
            return { success: false, message: `备份文件不存在: ${filename}` };
        }
        // 安全检查：防止路径穿越攻击
        if (filename.includes('..') || filename.includes('/') || filename.includes('\\')) {
            return { success: false, message: '非法的文件名' };
        }
        const data = JSON.parse(fs.readFileSync(backupPath, 'utf8'));
        // 恢复前先对当前数据做一次备份
        console.log(`[恢复] 恢复前先创建当前数据的安全备份...`);
        createFullBackup();
        // 执行恢复
        for (const key in data) {
            saveData(key, data[key]);
        }
        console.log(`[恢复] 从备份恢复数据成功: ${filename}`);
        return { success: true, message: `从备份 ${filename} 恢复成功`, restoredKeys: Object.keys(data) };
    } catch (e) {
        console.error(`[恢复] 从备份恢复失败 (${filename}):`, e.message);
        return { success: false, message: `恢复失败: ${e.message}` };
    }
}

// 删除备份文件
function deleteBackupFile(filename) {
    try {
        // 安全检查
        if (filename.includes('..') || filename.includes('/') || filename.includes('\\')) {
            return { success: false, message: '非法的文件名' };
        }
        const backupPath = path.join(BACKUP_DIR, filename);
        if (!fs.existsSync(backupPath)) {
            return { success: false, message: `备份文件不存在: ${filename}` };
        }
        fs.unlinkSync(backupPath);
        console.log(`[删除] 备份文件已删除: ${filename}`);
        return { success: true, message: `备份文件 ${filename} 已删除` };
    } catch (e) {
        console.error(`[删除] 删除备份失败 (${filename}):`, e.message);
        return { success: false, message: `删除失败: ${e.message}` };
    }
}

app.get('/api/data/:key', (req, res) => {
    const data = loadData(req.params.key);
    res.json({ success: true, data: data });
});

app.post('/api/data/:key', (req, res) => {
    const key = req.params.key;
    console.log('收到保存请求，key:', key);
    console.log('收到的数据:', req.body);

    // 保存前自动创建备份
    console.log(`[自动备份] 保存 ${key} 前创建备份...`);
    const backupFilename = createSingleBackup(key);
    if (backupFilename) {
        console.log(`[自动备份] 备份成功: ${backupFilename}`);
    } else {
        console.log(`[自动备份] 备份跳过（数据文件可能不存在）`);
    }

    const success = saveData(key, req.body);
    
    // 如果是新闻数据，自动同步到前端的 news_data.json
    if (key === 'newsData' && success) {
        const frontendNewsPath = path.join(__dirname, '..', 'news_data.json');
        try {
            fs.writeFileSync(frontendNewsPath, JSON.stringify(req.body, null, 2), 'utf8');
            console.log(`[同步] 新闻数据已同步到前端 news_data.json`);
        } catch (e) {
            console.error(`[同步] 同步到前端 news_data.json 失败:`, e.message);
        }
    }
    
    const message = success ? '保存成功' : '保存失败，请检查服务器日志';
    res.json({ success: success, message: message });
});

app.get('/api/data', (req, res) => {
    const allData = {};
    for (const key in DEFAULT_DATA) {
        allData[key] = loadData(key);
    }
    res.json({ success: true, data: allData });
});

app.post('/api/upload', (req, res) => {
    const { filename, content } = req.body;
    const MAX_UPLOAD_SIZE = 10 * 1024 * 1024; // 10MB
    const ALLOWED_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

    try {
        if (!content) {
            return res.json({ success: false, message: '图片数据为空' });
        }

        // 提取文件类型
        let fileType = 'jpg';
        const typeMatch = content.match(/^data:image\/(\w+);/);
        if (typeMatch) {
            fileType = typeMatch[1].toLowerCase();
            if (fileType === 'jpeg') fileType = 'jpg';
        }

        // 类型白名单校验
        if (!ALLOWED_TYPES.includes(fileType)) {
            return res.json({ success: false, message: `不支持的文件类型: ${fileType}` });
        }

        // 安全文件名处理
        let safeFilename = filename || `uploads/news_${Date.now()}_${Math.random().toString(36).slice(2, 8)}.${fileType}`;
        safeFilename = path.basename(safeFilename); // 剥离所有目录路径

        // 二次验证：只允许安全字符
        if (!/^[\w\-\.]+$/.test(safeFilename) || /\.\./.test(safeFilename)) {
            return res.json({ success: false, message: '非法的文件名' });
        }

        // 确保扩展名在白名单中
        const ext = path.extname(safeFilename).slice(1).toLowerCase();
        if (!ALLOWED_TYPES.includes(ext)) {
            safeFilename = safeFilename.replace(/\.[^.]+$/, '') + '.' + fileType;
        }

        // Base64解码
        const base64Data = content.replace(/^data:image\/\w+;base64,/, '');
        const buffer = Buffer.from(base64Data, 'base64');

        if (!buffer || buffer.length === 0) {
            return res.json({ success: false, message: 'Base64解码失败或数据为空' });
        }

        // 文件大小限制
        if (buffer.length > MAX_UPLOAD_SIZE) {
            return res.json({ success: false, message: `文件过大（最大${MAX_UPLOAD_SIZE / 1024 / 1024}MB）` });
        }

        // 安全路径：强制写入 uploads 目录
        const uploadsDir = path.join(__dirname, '..', 'uploads');
        if (!fs.existsSync(uploadsDir)) {
            fs.mkdirSync(uploadsDir, { recursive: true });
        }
        const filePath = path.join(uploadsDir, safeFilename);

        fs.writeFileSync(filePath, buffer);
        console.log(`[上传] 文件已保存: ${safeFilename} (${buffer.length} bytes)`);

        res.json({
            success: true,
            message: '上传成功',
            url: '/' + (filename.startsWith('uploads/') ? '' : 'uploads/') + safeFilename,
            filename: safeFilename,
            size: buffer.length
        });
    } catch (e) {
        console.error('上传失败:', e);
        res.json({ success: false, message: '上传失败: ' + e.message });
    }
});

// ==================== 备份API接口 ====================

// POST /api/backup - 手动创建全量备份
app.post('/api/backup', (req, res) => {
    console.log('[API] 收到全量备份请求');
    const backupFilename = createFullBackup();
    if (backupFilename) {
        const backups = getBackupList();
        res.json({
            success: true,
            message: `全量备份成功: ${backupFilename}`,
            backups: backups
        });
    } else {
        res.status(500).json({
            success: false,
            message: '全量备份失败，请检查服务器日志'
        });
    }
});

// GET /api/backup - 列出所有备份文件（按时间倒序）
app.get('/api/backup', (req, res) => {
    console.log('[API] 收到获取备份列表请求');
    const backups = getBackupList();
    res.json({
        success: true,
        backups: backups,
        count: backups.length
    });
});

// POST /api/backup/restore - 从指定备份恢复数据
app.post('/api/backup/restore', (req, res) => {
    const { filename } = req.body;
    console.log(`[API] 收到恢复请求，目标备份: ${filename}`);
    if (!filename) {
        return res.status(400).json({
            success: false,
            message: '请指定要恢复的备份文件名 (filename)'
        });
    }
    const result = restoreFromBackup(filename);
    if (result.success) {
        res.json(result);
    } else {
        res.status(400).json(result);
    }
});

// DELETE /api/backup/:filename - 删除指定备份文件
app.delete('/api/backup/:filename', (req, res) => {
    const filename = req.params.filename;
    console.log(`[API] 收到删除备份请求: ${filename}`);
    const result = deleteBackupFile(filename);
    if (result.success) {
        res.json(result);
    } else {
        res.status(404).json(result);
    }
});

// 根路由测试
app.get('/', (req, res) => {
    res.json({ 
        success: true, 
        message: '学生志愿教导大队管理后台 API 服务运行正常',
        timestamp: new Date().toISOString()
    });
});

app.get('/api/health', (req, res) => {
    res.json({ success: true, message: '服务运行正常' });
});

app.listen(PORT, '0.0.0.0', () => {
    console.log('========================================');
    console.log('   学生志愿教导大队管理后台 API');
    console.log('========================================');
    console.log(`服务地址: http://localhost:${PORT}`);
    console.log('数据目录:', DATA_DIR);
    console.log('----------------------------------------');
    initDefaultData(); // 初始化默认数据
    console.log('----------------------------------------');
    console.log('服务已启动，等待请求...');
    console.log('========================================');
});
