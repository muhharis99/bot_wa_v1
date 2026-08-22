<?php

final class XlsxScheduleReader
{
    private array $sharedStrings = [];

    public function read(string $file): array
    {
        if (!class_exists('ZipArchive')) throw new RuntimeException('Ekstensi PHP ZipArchive belum aktif. Aktifkan extension=zip terlebih dahulu.');
        $zip = new ZipArchive();
        if ($zip->open($file) !== true) throw new RuntimeException('File XLSX tidak dapat dibuka atau rusak.');
        try {
            $this->sharedStrings = $this->loadSharedStrings($zip);
            $sheetPath = $this->firstWorksheetPath($zip);
            $xml = $zip->getFromName($sheetPath);
            if ($xml === false) throw new RuntimeException('Worksheet pertama tidak ditemukan pada file XLSX.');
            return $this->parseSheet($xml);
        } finally { $zip->close(); }
    }

    public function detectHeader(array $rows): array
    {
        $aliases = [
            'tanggal' => ['tgl','tanggal','date','tanggal kerja'],
            'bulan' => ['bulan','month','bln'],
            'hari' => ['hari','day','nama hari'],
            'shift' => ['jadwal','shift','kode shift','dinas'],
            'jam_masuk' => ['jam masuk','masuk','jam kerja masuk','start'],
            'jam_berangkat' => ['jam berangkat','berangkat','jam berangkat kerja','departure'],
        ];
        foreach (array_slice($rows, 0, 20, true) as $rowIndex => $row) {
            $map = [];
            foreach ($row as $col => $value) {
                $label = $this->normalizeText((string)$value);
                foreach ($aliases as $key => $names) if (in_array($label, $names, true)) $map[$key] = $col;
            }
            if (isset($map['tanggal'], $map['shift']) && (isset($map['bulan']) || $this->rowLooksLikeFullDateHeader($row, $map))) return ['row_index' => $rowIndex, 'columns' => $map];
        }
        throw new RuntimeException('Header Excel tidak dikenali. Minimal diperlukan kolom Tgl/Tanggal, Jadwal/Shift, dan Bulan atau tanggal lengkap.');
    }

    public function normalizeSchedule(array $rows, ?int $year = null): array
    {
        $header = $this->detectHeader($rows);
        $dataRows = array_slice($rows, $header['row_index'] + 1, null, true);
        $year = $year ?: $this->suggestYear($dataRows, $header['columns']);
        $result = []; $seen = [];
        foreach ($dataRows as $excelRow => $row) {
            if ($this->isEmptyRow($row)) continue;
            $warnings = [];
            $date = $this->resolveDate($row, $header['columns'], $year);
            if (!$date) { $result[] = ['excel_row'=>$excelRow+1,'valid'=>false,'error'=>'Tanggal tidak dapat dibaca.','raw'=>$row]; continue; }
            $shiftRaw = (string)($row[$header['columns']['shift']] ?? '');
            $shift = $this->normalizeShift($shiftRaw);
            if ($shift === null) { $result[] = ['excel_row'=>$excelRow+1,'valid'=>false,'error'=>"Shift '{$shiftRaw}' tidak dikenali.",'tanggal'=>$date->format('Y-m-d'),'raw'=>$row]; continue; }
            $jam = null;
            if ($shift !== 'L' && isset($header['columns']['jam_berangkat'])) {
                $jam = $this->normalizeTime($row[$header['columns']['jam_berangkat']] ?? null);
                if (!$jam) $warnings[] = 'Jam berangkat kosong/tidak terbaca; jadwal akan berstatus Belum diatur.';
            }
            $hariExcel = isset($header['columns']['hari']) ? trim((string)($row[$header['columns']['hari']] ?? '')) : '';
            if ($hariExcel !== '') {
                $expected = $this->dayName((int)$date->format('N'));
                if ($this->normalizeDay($hariExcel) !== $this->normalizeDay($expected)) $warnings[] = "Nama hari Excel '{$hariExcel}' tidak cocok dengan {$expected}.";
            }
            $dateKey = $date->format('Y-m-d');
            if (isset($seen[$dateKey])) $warnings[] = 'Tanggal duplikat di Excel; baris terakhir akan dipakai saat import.';
            $seen[$dateKey] = true;
            $result[] = [
                'excel_row'=>$excelRow+1,'valid'=>true,'tanggal'=>$dateKey,'hari'=>$this->dayName((int)$date->format('N')),
                'hari_excel'=>$hariExcel,'shift'=>$shift,'jam_berangkat'=>$shift==='L'?null:$jam,
                'jam_masuk'=>isset($header['columns']['jam_masuk'])?$this->normalizeTime($row[$header['columns']['jam_masuk']]??null):null,'warnings'=>$warnings,
            ];
        }
        return ['year'=>$year,'header'=>$header,'rows'=>$result];
    }

    public function suggestYear(array $rows, array $columns, ?int $centerYear = null): int
    {
        $centerYear = $centerYear ?: (int)date('Y');
        if (!isset($columns['hari'])) return $centerYear;
        $bestYear = $centerYear; $bestScore = -1;
        for ($year=$centerYear-5; $year<=$centerYear+5; $year++) {
            $score=0; $checked=0;
            foreach (array_slice($rows,0,40,true) as $row) {
                $hari=trim((string)($row[$columns['hari']]??'')); if($hari==='') continue;
                $date=$this->resolveDate($row,$columns,$year); if(!$date) continue;
                $checked++; if($this->normalizeDay($hari)===$this->normalizeDay($this->dayName((int)$date->format('N')))) $score++;
            }
            $weighted=$checked?$score/$checked:0;
            if($weighted>$bestScore){$bestScore=$weighted;$bestYear=$year;}
        }
        return $bestYear;
    }

    private function firstWorksheetPath(ZipArchive $zip): string
    {
        $workbook=$zip->getFromName('xl/workbook.xml'); $rels=$zip->getFromName('xl/_rels/workbook.xml.rels');
        if($workbook===false||$rels===false) return 'xl/worksheets/sheet1.xml';
        $wb=@simplexml_load_string($workbook); $rl=@simplexml_load_string($rels); if(!$wb||!$rl) return 'xl/worksheets/sheet1.xml';
        $sheets=$wb->xpath('//*[local-name()="sheet"]'); if(!$sheets) return 'xl/worksheets/sheet1.xml';
        $attrs=$sheets[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships'); $rid=(string)$attrs['id'];
        $relationships=$rl->xpath('//*[local-name()="Relationship"]') ?: [];
        foreach($relationships as $rel){ if((string)$rel['Id']===$rid){$target=ltrim((string)$rel['Target'],'/'); if(str_starts_with($target,'xl/')) return $target; return 'xl/'.preg_replace('#^\.\./#','',$target);} }
        return 'xl/worksheets/sheet1.xml';
    }

    private function loadSharedStrings(ZipArchive $zip): array
    {
        $xml=$zip->getFromName('xl/sharedStrings.xml'); if($xml===false) return [];
        $sx=@simplexml_load_string($xml); if(!$sx) return [];
        $out=[]; $items=$sx->xpath('//*[local-name()="si"]') ?: [];
        foreach($items as $si){ $texts=$si->xpath('.//*[local-name()="t"]') ?: []; $text=''; foreach($texts as $t)$text.=(string)$t; $out[]=$text; }
        return $out;
    }

    private function parseSheet(string $xml): array
    {
        $sx=@simplexml_load_string($xml); if(!$sx) throw new RuntimeException('XML worksheet tidak valid.');
        $rows=[]; $rowNodes=$sx->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [];
        foreach($rowNodes as $row){$values=[];$cells=$row->xpath('./*[local-name()="c"]')?:[];foreach($cells as $cell){$ref=(string)$cell['r'];$col=$this->columnIndex($ref);$type=(string)$cell['t'];$value=null;
            if($type==='inlineStr'){$texts=$cell->xpath('.//*[local-name()="t"]')?:[];$value='';foreach($texts as $t)$value.=(string)$t;}
            else{$vNodes=$cell->xpath('./*[local-name()="v"]')?:[];$raw=$vNodes?(string)$vNodes[0]:'';$value=$type==='s'?($this->sharedStrings[(int)$raw]??''):$raw;if($type==='n'||($type===''&&is_numeric($raw)))$value=$raw===''?null:(float)$raw;}
            $values[$col]=$value;}
            if($values){$max=max(array_keys($values));$dense=[];for($i=0;$i<=$max;$i++)$dense[$i]=$values[$i]??null;$rows[(int)$row['r']-1]=$dense;}
        }
        return $rows;
    }

    private function columnIndex(string $ref): int { preg_match('/^[A-Z]+/i',$ref,$m);$letters=strtoupper($m[0]??'A');$index=0;foreach(str_split($letters) as $ch)$index=$index*26+(ord($ch)-64);return $index-1; }

    private function resolveDate(array $row,array $columns,int $year): ?DateTimeImmutable
    {
        $raw=$row[$columns['tanggal']]??null;
        if(is_numeric($raw)&&(float)$raw>20000){$base=new DateTimeImmutable('1899-12-30');return $base->modify('+'.(int)floor((float)$raw).' days');}
        if(is_string($raw)&&preg_match('/\d{1,4}[-\/]\d{1,2}[-\/]\d{1,4}/',$raw)){try{return new DateTimeImmutable(str_replace('/','-',$raw));}catch(Throwable){}}
        $day=(int)$raw;$month=isset($columns['bulan'])?$this->monthNumber((string)($row[$columns['bulan']]??'')):null;if($day<1||$day>31||!$month)return null;
        $date=DateTimeImmutable::createFromFormat('!Y-n-j',"{$year}-{$month}-{$day}");return $date&&(int)$date->format('j')===$day&&(int)$date->format('n')===$month?$date:null;
    }

    private function normalizeTime(mixed $value): ?string
    {
        if($value===null||$value==='')return null;
        if(is_numeric($value)){$fraction=(float)$value-floor((float)$value);$minutesTotal=(int)round($fraction*1440)%1440;$hours=intdiv($minutesTotal,60);$minutes=$minutesTotal%60;return sprintf('%02d:%02d',$hours,$minutes);}
        $text=trim((string)$value);if(preg_match('/^(\d{1,2})[:.]([0-5]\d)(?::[0-5]\d)?$/',$text,$m)){$h=(int)$m[1];if($h<=23)return sprintf('%02d:%02d',$h,(int)$m[2]);}return null;
    }

    private function normalizeShift(string $value): ?string
    {
        $v=strtoupper($this->normalizeText($value));return match($v){'P','PAGI','MORNING'=>'P','W','SIANG','DAY'=>'W','S','SORE','EVENING'=>'S','L','LIBUR','OFF','HOLIDAY'=>'L',default=>null};
    }

    private function monthNumber(string $value): ?int
    {
        $v=$this->normalizeText($value);$map=['jan'=>1,'januari'=>1,'january'=>1,'feb'=>2,'februari'=>2,'february'=>2,'mar'=>3,'maret'=>3,'march'=>3,'apr'=>4,'april'=>4,'mei'=>5,'may'=>5,'jun'=>6,'juni'=>6,'june'=>6,'jul'=>7,'juli'=>7,'july'=>7,'agu'=>8,'agt'=>8,'agustus'=>8,'aug'=>8,'august'=>8,'sep'=>9,'sept'=>9,'september'=>9,'okt'=>10,'oct'=>10,'oktober'=>10,'october'=>10,'nov'=>11,'november'=>11,'des'=>12,'dec'=>12,'desember'=>12,'december'=>12];if(isset($map[$v]))return $map[$v];if(ctype_digit($v)&&(int)$v>=1&&(int)$v<=12)return(int)$v;return null;
    }

    private function normalizeDay(string $value): string
    {
        $v=$this->normalizeText($value);return match($v){'senin','monday','mon'=>'senin','selasa','tuesday','tue'=>'selasa','rabu','wednesday','wed'=>'rabu','kamis','thursday','thu'=>'kamis','jumad','jumat','jum\'at','friday','fri'=>'jumat','sabtu','saturday','sat'=>'sabtu','minggu','ahad','sunday','sun'=>'minggu',default=>$v};
    }

    private function dayName(int $n): string { return [1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu',7=>'Minggu'][$n]; }
    private function normalizeText(string $value): string { return strtolower(trim(preg_replace('/\s+/',' ',$value))); }
    private function isEmptyRow(array $row): bool { foreach($row as $v)if($v!==null&&trim((string)$v)!=='')return false;return true; }
    private function rowLooksLikeFullDateHeader(array $row,array $map): bool { return isset($map['tanggal']); }
}
