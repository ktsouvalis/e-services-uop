@if($username=='preview')
<div>
    <p><strong>Θέμα:</strong> {{$sheetmailer->subject}}</p>
<div>
@endif
{!! $sheetmailer->body !!}
</div>

@if($additionalData)
<div>
    <p>{!! strip_tags($additionalData,'<p><a><strong><span><i><em><b><u><ul><ol><li><br>') !!}</p>
</div>
@endif

<div>
<p>{{$sheetmailer->signature}}</p>
</div>
