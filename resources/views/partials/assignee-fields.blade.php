{{-- Up to three people, each with their own part. $current: list of ['user_id' => .., 'note' => ..] --}}
@php($rows = old('assignees') ?? $current)
<fieldset class="channels">
    <legend>ვინ გააკეთებს? <small>მაქსიმუმ {{ \App\Models\Task::MAX_ASSIGNEES }} ადამიანი — თითოეულს თავისი ნაწილი</small></legend>
    @for ($i = 0; $i < \App\Models\Task::MAX_ASSIGNEES; $i++)
        @php($row = $rows[$i] ?? [])
        <div class="assignee-row">
            <label class="sr-only" for="as-{{ $uid }}-{{ $i }}">შემსრულებელი {{ $i + 1 }}</label>
            <select id="as-{{ $uid }}-{{ $i }}" name="assignees[{{ $i }}][user_id]">
                <option value="">{{ $i === 0 ? '— აირჩიე შემსრულებელი' : '— კიდევ ერთი (არ არის აუცილებელი)' }}</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}" @selected((string) ($row['user_id'] ?? '') === (string) $u->id)>{{ $u->name }} · {{ $u->roleLabel() }}</option>
                @endforeach
            </select>
            <label class="sr-only" for="as-note-{{ $uid }}-{{ $i }}">რა უნდა გააკეთოს</label>
            <input id="as-note-{{ $uid }}-{{ $i }}" name="assignees[{{ $i }}][note]" maxlength="300"
                   placeholder="რა უნდა გააკეთოს? მაგ. პოსტერის დიზაინი / ფოტოები / ტექსტი" value="{{ $row['note'] ?? '' }}">
        </div>
    @endfor
</fieldset>
