# Post-Purchase Survey for WooCommerce — Developer Notes

Shows a single "How did you hear about us?" survey on the WooCommerce order-received page and reports response counts. See `readme.txt` for user-facing documentation.

## Data model

Questions are a custom post type (`ppsfw_question`): the post title is the question, the answer options live in the `_ppsfw_answers` post meta (arrays of `value`, `label`, `enabled`), and per-question settings live in `_ppsfw_question_type` (`single`; `multiple` is reserved for Pro), `_ppsfw_other` (whether the "Other" option is offered), and `_ppsfw_other_label`. The active survey configuration (enabled flag, ordered selected question IDs, display position, thank-you message) is stored in the `ppsfw_survey` option; the `ppsfw_settings` option holds the uninstall data preference.

Responses are stored in a custom table `{$wpdb->prefix}ppsfw_responses`:

| Column | Type | Notes |
| --- | --- | --- |
| `id` | BIGINT | PK, auto-increment |
| `order_id` | BIGINT | Indexed via unique key |
| `question_id` | INT | The question post ID — the seam for multi-question flows and per-question reporting |
| `answer_value` | VARCHAR(191) | Stable key/slug of the option |
| `answer_label` | TEXT | Label at the time of the answer (edits don't rewrite history); TEXT because labels are freeform admin input |
| `is_other` | TINYINT | Whether the "Other" option was chosen |
| `other_text` | TEXT | Free text for "Other" (nullable) |
| `created_at` | DATETIME | UTC, indexed |

A unique index on (`order_id`, `question_id`, `answer_value`) blocks exact-duplicate answers at the database level. "One response per order per question" is enforced in the application: `ResponseRepository::insert()` uses an atomic `INSERT ... SELECT ... WHERE NOT EXISTS` guard, and Pro multi-choice questions pass `$one_per_question = false` to store several answer rows per question — no schema change needed when Pro is enabled or disabled.

The chosen answer is also written to order meta via the order object (HPOS-safe):

- `_ppsfw_answer` — the answer label
- `_ppsfw_answer_value` — the stable answer value
- `_ppsfw_other_text` — free text when "Other" was chosen

## Filters

### `ppsfw_get_questions( $questions )`

The active survey questions, keyed by question (post) ID. Each question is an array of `id`, `text`, `status`, `type`, `options`, `other_enabled`, `other_label`. Only selected, published questions with enabled answers are included.

### `ppsfw_max_questions( $max )`

The maximum number of questions in the active survey. The free version returns `1`; a Pro tier raises this to enable multi-question flows.

### `ppsfw_allowed_question_types( $types )`

The question types that can be stored and rendered. Version 1 shows no type UI and stamps every question as `single` (`_ppsfw_question_type` meta); a future Pro tier adds `'multiple'` and restores the type selector. The front end currently renders single choice only.

### `ppsfw_answer_options( $options, $question_id )`

The answer options for a question. Each option is an array of `value`, `label`, `enabled`.

### `ppsfw_should_display( $display, $order )`

Whether the survey should render for an order on the order-received page. `$order` is the `WC_Order`.

### `ppsfw_response_data( $data, $order )`

The response data array (`order_id`, `question_id`, `answer_value`, `answer_label`, `is_other`, `other_text`, `created_at`) just before it is saved.

### `ppsfw_report_query_args( $args, $range )`

The `wc_get_orders()` arguments used to count orders for the report's response-rate metric. `$range` contains the resolved date range.

## Actions

### `ppsfw_after_response_saved( $order_id, $data )`

Fires after a response row and its order meta have been saved.

### `ppsfw_render_before_form( $order )` / `ppsfw_render_after_form( $order )`

Fire immediately before/after the survey form is rendered on the order-received page.

## Styling

All front-end spacing and color decisions are exposed as prefixed CSS variables, defined on `:root` in `dist/css/front.css`. Theme developers can override them at `:root`, or on `.ppsfw-survey` (which wins regardless of stylesheet load order):

```css
.ppsfw-survey {
	--ppsfw-survey-border-color: #333;
	--ppsfw-survey-radius: 0;
	--ppsfw-survey-background: #fafafa;
}
```

| Variable | Default | Controls |
| --- | --- | --- |
| `--ppsfw-survey-margin-block` | `2em` | Vertical margin around the survey box |
| `--ppsfw-survey-padding` | `1.25em 1.5em` | Survey box padding |
| `--ppsfw-survey-border-width` | `1px` | Survey box border width |
| `--ppsfw-survey-border-color` | `rgba(0, 0, 0, 0.15)` | Survey box border color |
| `--ppsfw-survey-radius` | `4px` | Survey box corner radius |
| `--ppsfw-survey-background` | `transparent` | Survey box background |
| `--ppsfw-question-font-weight` | `600` | Question (legend) weight |
| `--ppsfw-question-spacing` | `0.75em` | Space below the question |
| `--ppsfw-options-spacing` | `1em` | Space below the answer list |
| `--ppsfw-option-spacing` | `0.5em` | Space between answers |
| `--ppsfw-option-input-gap` | `0.4em` | Gap between radio and label |
| `--ppsfw-other-indent` | `1.6em` | Indent of the "Other" text field |
| `--ppsfw-other-spacing` | `0.4em` | Space above the "Other" text field |
| `--ppsfw-other-input-max-width` | `320px` | Max width of the "Other" text field |
| `--ppsfw-error-color` | `#b32d2e` | Inline error message color |
| `--ppsfw-error-gap` | `1em` | Gap before the inline error message |

The admin stylesheet uses the same pattern with `--ppsfw-admin-*` variables.

## Building

```
composer install
npm install
npm run prod
```

Front-end and admin assets are built from `src/` into `dist/` with Laravel Mix.
