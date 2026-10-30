{{-- Rekap per satpam: nama, jumlah nota, total, persentase --}}
<div style="margin-top:.75rem">
    <div style="{{ $redup }};margin-bottom:.25rem">Rekap per satpam</div>
    <table style="width:100%;border-collapse:collapse;font-size:.9rem">
        @forelse ($rekap as $r)
            <tr>
                <td style="{{ $sel }}">{{ $r['nama'] }}</td>
                <td style="{{ $sel }};text-align:right;opacity:.65">{{ $r['nota'] }} nota</td>
                <td style="{{ $sel }};text-align:right;opacity:.65">{{ $r['persen'] }}%</td>
                <td style="{{ $sel }};text-align:right;font-weight:600">{{ $fmt($r['total']) }}</td>
            </tr>
        @empty
            <tr><td style="{{ $sel }};opacity:.65">Belum ada penjualan.</td></tr>
        @endforelse
    </table>
</div>