<div>
{!! $sheetmailer->body !!}
</div>

@if($additionalData)
<div>
<p><i>{{$additionalData}}</i></p>
</div>
@endif

<div>
<p><i>{{$sheetmailer->signature}}</i></p>
</div>
