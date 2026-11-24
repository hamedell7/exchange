import { useState } from 'react';
import { Eye, EyeOff } from 'lucide-react';

export function Field({ label, type = 'text', error, icon: Icon, hint, ...props }) {
  const [visible, setVisible] = useState(false);
  const isPassword = type === 'password';
  const inputType = isPassword ? (visible ? 'text' : 'password') : type;

  return (
    <div className={`field-group ${error ? 'has-error' : ''}`}>
      <label className="field-label" htmlFor={props.id}>{label}</label>
      <div className="field-control">
        {Icon ? <Icon className="field-icon" size={18} aria-hidden="true" /> : null}
        <input
          {...props}
          id={props.id}
          type={inputType}
          aria-invalid={Boolean(error)}
          aria-describedby={error ? `${props.id}-error` : hint ? `${props.id}-hint` : undefined}
        />
        {isPassword ? (
          <button
            className="field-visibility"
            type="button"
            onClick={() => setVisible((value) => !value)}
            aria-label={visible ? 'پنهان کردن رمز عبور' : 'نمایش رمز عبور'}
          >
            {visible ? <EyeOff size={18} /> : <Eye size={18} />}
          </button>
        ) : null}
      </div>
      {error ? <p className="field-error" id={`${props.id}-error`}>{error}</p> : null}
      {!error && hint ? <p className="field-hint" id={`${props.id}-hint`}>{hint}</p> : null}
    </div>
  );
}
