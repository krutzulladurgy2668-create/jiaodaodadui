$f = 'c:\Users\Shangqi Yin\Documents\trae_projects\ysq\index.html'
$bytes = [System.IO.File]::ReadAllBytes($f)
$text = [System.IO.File]::ReadAllText($f, [System.Text.Encoding]::UTF8)
$lines = $text -split "`n"

Write-Host "=== File Info ==="
Write-Host "Total bytes: $($bytes.Length)"
Write-Host "Total lines: $($lines.Length)"
Write-Host "First 5 bytes (hex): $($bytes[0].ToString('X2')) $($bytes[1].ToString('X2')) $($bytes[2].ToString('X2')) $($bytes[3].ToString('X2')) $($bytes[4].ToString('X2'))"

# Check for zero-width chars
$zwsCount = ([regex]::Matches($text, '[\u200B-\u200F\uFEFF]')).Count
Write-Host "Zero-width chars total: $zwsCount"

# Check for non-ASCII in img src attributes
$badSrc = [regex]::Matches($text, 'src\s*=\s*"[^"]*"')
foreach ($m in $badSrc) {
    $val = $m.Value
    if ($val -match '[^\x20-\x7E\u4e00-\u9fff]') {
        Write-Host "SUSPICIOUS src at line: $($text.Substring(0, $m.Index).Split("`n").Length) -> $val"
    }
}

# Check line 1926-1930 area for hidden chars
Write-Host ""
Write-Host "=== Around deletion area (L1925-1932) ==="
for ($i = 1924; $i -lt [Math]::Min(1934, $lines.Length); $i++) {
    $line = $lines[$i]
    $hex = ""
    foreach ($ch in $line.ToCharArray()) {
        $hex += [int]$ch.ToString("X4") + " "
    }
    Write-Host "L$($i+1) [$($line.Length)chars] hex: $hex"
}

# Count main tags
$mainOpen = ([regex]::Matches($text, '<main')).Count
$mainClose = ([regex]::Matches($text, '</main>')).Count
Write-Host ""
Write-Host "<main> tags: $mainOpen"
Write-Host "</main> tags: $mainClose"
