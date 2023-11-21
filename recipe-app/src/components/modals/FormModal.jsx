import React, { useState, useEffect, useMemo } from 'react';
import Modal from './Modal';
import Button from '../common/Button';
import FormInput from '../common/FormInput';
import FormTextArea from '../common/FormTextArea';
import FormFileInput from '../common/FormFileInput';
import ApiService from '../../services/ApiService';
import './FormModal.scss';

const FormModal = ({ isOpen, onClose, onSubmit, config, initialData, mode }) => {

    // Initialize formData with default values
    const defaultFormData = useMemo(() => {
        return config.reduce((acc, field) => {
            acc[field.name] = '';
            return acc;
        }, {});
    }, [config]);

    const [formData, setFormData] = useState(defaultFormData);

    // Set formData to initialData when in edit mode
    useEffect(() => {
        if (mode === 'edit' && initialData) {
            const newFormData = { ...defaultFormData };
            config.forEach(field => {
                newFormData[field.name] = initialData[field.mapTo || field.name] || '';
            });
            setFormData(newFormData);
        } else {
            setFormData(defaultFormData);
        }
    }, [config, defaultFormData, initialData, mode]);

    
    const handleChange = (e) => {
        setFormData({ ...formData, [e.target.name]: e.target.value });
    }

    const handleFileUpload = async (file) => {
        const formData = new FormData();
        formData.append('file', file);
        try{
            const response = await ApiService.uploadFile(formData);
            if(response.url){
                setFormData(prevFormData => ({
                    ...prevFormData,
                    recipe_image_path: response.url
                }));
            } else {
                console.error('File upload response does not contain a URL.');
            }
        } catch (error) {
            console.error('File upload error:', error);
        }
        };

    const renderFormFields = () => {
        return config.map((field) => {
            if (field.name.includes('id')) {
                return null;
            }
            switch (field.type) {
                case 'text':
                    return (
                        <FormInput
                            key={field.name} 
                            {...field}
                            name={field.name}
                            label={field.label}
                            value={formData[field.name]}
                            onChange={handleChange}
                        />
                    );
                case 'number':
                    return (
                        <FormInput
                            key={field.name} 
                            {...field}
                            name={field.name}
                            label={field.label}
                            value={formData[field.name]}
                            onChange={handleChange}
                        />
                    );
                case 'textarea':
                    return (
                        <FormTextArea
                            key={field.name} 
                            {...field}
                            name={field.name}
                            label={field.label}
                            value={formData[field.name]}
                            onChange={handleChange}
                        />
                    );
                case 'file':
                    return (
                        <FormFileInput
                            key={field.name} 
                            {...field}
                            name={field.name}
                            label={field.label}
                            onFileUpload={handleFileUpload}
                        />
                    );

                case 'email':
                    return (
                        <FormInput
                            key={field.name} 
                            {...field}
                            name={field.name}
                            label={field.label}
                            value={formData[field.name]}
                            onChange={handleChange}
                        />
                    );
                default:
                    return null;
            }
        });
    };

    return (
        <Modal isOpen={isOpen} onClose={onClose}>
            <form onSubmit={(e) => {
                e.preventDefault();
                console.log('Form data:', formData)
                const jsonFormData = JSON.stringify(formData);
                onSubmit(jsonFormData);
                onClose();
            }}>
                {renderFormFields()}
                <Button type="submit">{mode === 'edit' ? 'Save Changes' : 'Create'}</Button>
            </form>
        </Modal>
    );
};

export default FormModal;
