param(
  [string]$PluginDir = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path,
  [string]$OutZip = ""
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$pluginName = Split-Path $PluginDir -Leaf
if ([string]::IsNullOrWhiteSpace($OutZip)) {
  $OutZip = Join-Path (Split-Path $PluginDir -Parent) ($pluginName + ".zip")
}

# Load ignore patterns (WordPress.org release-style)
$ignoreFile = Join-Path $PluginDir "docs\distignore-list.txt"
$patterns = @()
if (Test-Path $ignoreFile) {
  $patterns = Get-Content $ignoreFile |
    ForEach-Object { $_.Trim() } |
    Where-Object { $_ -and -not $_.StartsWith("#") }
}

# Extra safe excludes (not in distignore list)
# `composer.json` requires only PHP, and `assetpilot.php` has a fallback autoloader,
# so `vendor/` is not needed for runtime.
$patterns += ".editorconfig"
$patterns += "vendor/"
$patterns += "scripts/"

function Test-Excluded {
  param(
    [Parameter(Mandatory = $true)][string]$RelPath,
    [Parameter(Mandatory = $true)][string[]]$Patterns
  )

  foreach ($pRaw in $Patterns) {
    if (-not $pRaw) { continue }

    $p = ($pRaw.Trim()).TrimStart("/") -replace "\\", "/"
    $r = ($RelPath -replace "\\", "/")

    if ($p.EndsWith("/")) {
      $prefix = $p.TrimEnd("/")
      if ($r -eq $prefix -or $r -like "$prefix/*") { return $true }
      continue
    }

    # Wildcards like "*.zip"
    if ($p.Contains("*") -or $p.Contains("?")) {
      if ($r -like $p) { return $true }
      continue
    }

    # Exact file/dir match
    if ($r -eq $p -or $r -like "$p/*") { return $true }
  }

  return $false
}

#
# ZipArchive lives in .NET assemblies; on some PS installs only loading
# FileSystem is not enough for ZipArchiveMode.
#
Add-Type -AssemblyName "System.IO.Compression" -ErrorAction SilentlyContinue
Add-Type -AssemblyName "System.IO.Compression.FileSystem" -ErrorAction SilentlyContinue

if (Test-Path $OutZip) { Remove-Item $OutZip -Force }

$zipStream = [System.IO.File]::Open($OutZip, [System.IO.FileMode]::Create)

# ZipArchiveMode::Create is typically the enum value 1.
#
# Fallback included for environments where ZipArchiveMode isn't resolvable.
$zipMode = 1
try {
  $zipMode = [System.IO.Compression.ZipArchiveMode]::Create
} catch {
  $zipMode = 1
}

$archive = New-Object System.IO.Compression.ZipArchive($zipStream, $zipMode)

try {
  $files = Get-ChildItem -Path $PluginDir -File -Recurse -Force

  foreach ($file in $files) {
    $rel = $file.FullName.Substring($PluginDir.Length + 1)
    if (Test-Excluded -RelPath $rel -Patterns $patterns) { continue }

    # Zip should contain the top-level plugin directory
    $entryName = ($pluginName + "/" + ($rel -replace "\\", "/"))
    $entry = $archive.CreateEntry($entryName, [System.IO.Compression.CompressionLevel]::Optimal)

    $entryStream = $entry.Open()
    $fileStream = $file.OpenRead()
    try {
      $fileStream.CopyTo($entryStream)
    } finally {
      $fileStream.Close()
      $entryStream.Close()
    }
  }
} finally {
  $archive.Dispose()
  $zipStream.Close()
}

Write-Host "Created: $OutZip"
