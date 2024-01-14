import React from "react";
import "./FormInput.scss";

const FormInput = ({ name, label, type = "text", value, onChange }) => (
    <div className="form-input">
        <label htmlFor={name} className="form-input__label">{label}</label>
        <input
            type={type}
            name={name}
            id={name}
            className="form-input__input"
            value={value}
            onChange={onChange}
        />
    </div>
);

export default FormInput;
