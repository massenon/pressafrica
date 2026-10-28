# Generates the episode audio (FR + EN) with the offline Windows speech voices, then encodes MP3 (mono, 64 kbps) with ffmpeg.
# Usage (from the repo root):  powershell -File tools/make_podcast_audio.ps1
# Output: prototype_html/assets/audio/<episode id>-fr.mp3 / -en.mp3. Replace with studio recordings at any time (same file names).
Add-Type -AssemblyName System.Speech
$root = Split-Path -Parent $PSScriptRoot
$data = Get-Content (Join-Path $root 'prototype_html/data/podcasts.json') -Raw -Encoding UTF8 | ConvertFrom-Json
$out  = Join-Path $root 'prototype_html/assets/audio'
$voice = @{ fr = 'Microsoft Hortense Desktop'; en = 'Microsoft Zira Desktop' }
$intro = @{ fr = 'Africa Global Press. {0}.'; en = 'Africa Global Press. {0}.' }
$outro = @{ fr = "Retrouvez l'article complet et tous nos podcasts sur Africa Global Press. Merci de votre écoute."; en = 'Read the full story and find all our podcasts on Africa Global Press. Thank you for listening.' }
foreach ($ep in $data.episodes) {
  $show = $data.shows | Where-Object { $_.id -eq $ep.show }
  foreach ($lg in 'fr', 'en') {
    $s = New-Object System.Speech.Synthesis.SpeechSynthesizer
    $s.SelectVoice($voice[$lg]); $s.Rate = -1
    $wav = Join-Path $out "$($ep.id)-$lg.wav"
    $s.SetOutputToWaveFile($wav)
    $text = ($intro[$lg] -f $show.$lg.title) + ' ' + $ep.$lg.title + '. ' + $ep.$lg.desc + ' ' + $outro[$lg]
    $s.Speak($text); $s.Dispose()
    ffmpeg -y -loglevel error -i $wav -ac 1 -b:a 64k (Join-Path $out "$($ep.id)-$lg.mp3")
    Remove-Item $wav
  }
}
Get-ChildItem $out -Filter *.mp3 | ForEach-Object { '{0} {1:N0} KB' -f $_.Name, ($_.Length / 1KB) }
