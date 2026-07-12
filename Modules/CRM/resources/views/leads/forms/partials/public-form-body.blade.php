@if($submitted)
    <div class="pf-success">
        <i class="fa fa-circle-check" aria-hidden="true"></i>
        <p>{{ $form->success_message ?: "Thanks! We'll be in touch soon." }}</p>
    </div>
@else
    @if($errors->any())
        <div class="pf-error" style="margin-bottom:14px;">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('crm.public-forms.submit', $form->token) }}" novalidate>
        @csrf
        @include('crm::leads.forms.partials.public-blocks', ['blocks' => $form->blocks ?? [], 'pathPrefix' => '', 'customFields' => $customFields])

        <button type="submit" class="pf-submit">{{ $form->submit_button_text ?: 'Submit' }}</button>
    </form>
@endif
