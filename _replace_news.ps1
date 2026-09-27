$jsonPath = 'c:\Users\Shangqi Yin\Documents\trae_projects\ysq\backend\data\news_data.json'
$htmlPath = 'c:\Users\Shangqi Yin\Documents\trae_projects\ysq\asd.html'

# 读取JSON数据
$jsonContent = Get-Content $jsonPath -Raw -Encoding UTF8
$data = $jsonContent | ConvertFrom-Json
Write-Host "Total items: $($data.Count)"

# 构建JS数组
$lines = @('const DEFAULT_NEWS = [')
foreach ($item in $data) {
    # 转义双引号和反斜杠
    $title = $item.title -replace '\\', '\\' -replace '"', '\"'
    $desc = $item.desc -replace '\\', '\\' -replace '"', '\"'
    $author = $item.author -replace '\\', '\\' -replace '"', '\"'
    $content = $item.content -replace '\\', '\\' -replace '"', '\"'

    $line = "            {id:$($item.id),title:`"$title`",desc:`"$desc`",date:`"$($item.date)`",author:`"$author`",views:$($item.views),status:`"$($item.status)`",content:`"$content`",images:[]}"
    $lines += $line
}
$lines += '        ];'

$newArray = $lines -join "`n"

# 读取HTML文件
$htmlContent = Get-Content $htmlPath -Raw -Encoding UTF8

# 替换DEFAULT_NEWS数组（从 "const DEFAULT_NEWS = [" 到下一个 "];"）
$pattern = '(?s)const DEFAULT_NEWS = \[.*?\];'
$replacement = $newArray
$newHtml = $htmlContent -replace $pattern, $replacement

# 写回文件
Set-Content $htmlPath -Value $newHtml -Encoding UTF8 -NoNewline
Write-Host "Done! Replaced DEFAULT_NEWS with $($data.Count) items"
