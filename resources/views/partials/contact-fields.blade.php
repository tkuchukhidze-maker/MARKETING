@use('App\Support\Studio')
<label class="field">სახელი<input name="name" required maxlength="150" value="{{ $c?->name }}"></label>
<div class="form-grid">
    <label class="field">ტიპი
        <select name="type" required>
            @foreach (Studio::CONTACT_TYPES as $key => $label)<option value="{{ $key }}" @selected($c?->type === $key)>{{ $label }}</option>@endforeach
        </select>
    </label>
    <label class="field">რესტორანი
        <select name="venue_id">
            <option value="">ორივე</option>
            @foreach ($venues as $v)<option value="{{ $v->id }}" @selected($c?->venue_id === $v->id)>{{ $v->name }}</option>@endforeach
        </select>
    </label>
    <label class="field">ტელეფონი<input name="phone" maxlength="40" value="{{ $c?->phone }}"></label>
    <label class="field">ელფოსტა<input type="email" name="email" value="{{ $c?->email }}"></label>
    <label class="field">Instagram<input name="instagram" maxlength="190" value="{{ $c?->instagram }}"></label>
    <label class="field">შემდეგი ნაბიჯი<input name="next_step" maxlength="190" value="{{ $c?->next_step }}"></label>
</div>
<label class="field">შენიშვნა<textarea name="notes" maxlength="3000">{{ $c?->notes }}</textarea></label>
