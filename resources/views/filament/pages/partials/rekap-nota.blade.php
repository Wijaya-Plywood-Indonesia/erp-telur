{{-- Daftar nota dalam satu siklus setoran --}}
<div style="margin-top:.6rem;overflow:auto;max-height:340px">
    <table style="width:100%;border-collapse:collapse;font-size:.82rem">
        <thead style="position:sticky;top:0;background:inherit">
            <tr style="opacity:.6;text-align:left">
                <th style="{{ $sel }}">No nota</th>
                <th style="{{ $sel }}">Tanggal</th>
                <th style="{{ $sel }}">Customer</th>
                <th style="{{ $sel }}">Satpam</th>
                <th style="{{ $sel }};text-align:right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($notas as $n)
                <tr>
                    <td style="{{ $sel }};white-space:nowrap">{{ $n['no_nota'] }}</td>
                    <td style="{{ $sel }};white-space:nowrap">{{ $tgl($n['tanggal']) }}</td>
                    <td style="{{ $sel }}">{{ $n['customer'] ?: '-' }}</td>
                    <td style="{{ $sel }}">{{ $n['satpam'] }}</td>
                    <td style="{{ $sel }};text-align:right;white-space:nowrap">{{ $fmt($n['total']) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="font-weight:700">
                <td style="{{ $sel }}" colspan="4">Total ({{ count($notas) }} nota)</td>
                <td style="{{ $sel }};text-align:right">{{ $fmt(collect($notas)->sum('total')) }}</td>
            </tr>
        </tfoot>
    </table>
</div>