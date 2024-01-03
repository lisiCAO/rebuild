import React from 'react';

import './FavoriteButton.scss';

const FavoriteButton = ({ isFavorited, onToggle }) => (
    <button 
        className={`favorite-button ${isFavorited ? 'favorite-button--favorited' : ''}`}
        onClick={onToggle}>
        {isFavorited ? '💗' : '🤍'} {/* 三元表达式 */}
    </button>
);

export default FavoriteButton;

// Path: recipe-app/src/components/common/FavoriteButton.jsx