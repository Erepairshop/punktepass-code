<?php
if (!defined('ABSPATH')) exit;

class PPV_Lang_Switcher {

    public static function render() {
        $langs = [
            'de' => ['name' => 'Deutsch', 'flag' => '🇩🇪'],
            'hu' => ['name' => 'Magyar', 'flag' => '🇭🇺'],
            'ro' => ['name' => 'Română', 'flag' => '🇷🇴']
        ];

        $current = PPV_Lang::$active ?? 'de';

        ob_start(); ?>
        <div class="ppv-lang-dropdown">
            <button class="ppv-lang-btn">
                <?php echo $langs[$current]['flag']; ?> <?php echo strtoupper($current); ?> ▼
            </button>
            <div class="ppv-lang-menu">
                <?php foreach ($langs as $code => $info): ?>
                    <?php if ($code !== $current): ?>
                        <a href="?ppv_lang=<?php echo $code; ?>" class="ppv-lang-item">
                            <?php echo $info['flag']; ?> <?php echo $info['name']; ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <style>
           .ppv-lang-dropdown {
  position: absolute; /* már nem fixed! */
    display: flex;
    justify-content: center;
    margin-top: 10px;
    z-index: 10;

    top: 30px;

    left: 50%;
    transform: translateX(-50%);
    font-family: "Poppins", sans-serif;
}

.ppv-lang-btn {
    background: rgba(255, 0, 128, 0.15);
    color: #ff66b3;
    border: 1px solid rgba(255, 0, 128, 0.4);
    border-radius: 10px;
    padding: 6px 14px;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.25s ease;
}

.ppv-lang-btn:hover {
    background: rgba(255, 0, 128, 0.25);
    box-shadow: 0 0 12px rgba(255, 0, 128, 0.4);
}

.ppv-lang-menu {
    display: none;
    position: absolute;
    top: 38px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.9);
    border: 1px solid rgba(255, 0, 128, 0.4);
    border-radius: 10px;
    box-shadow: 0 0 25px rgba(255, 0, 128, 0.2);
    min-width: 150px;
    text-align: center;
    padding: 6px 0;
}

.ppv-lang-dropdown:hover .ppv-lang-menu {
    display: block;
}

.ppv-lang-item {
    display: block;
    padding: 8px 14px;
    color: #ff99cc;
    text-decoration: none;
    font-size: 14px;
    transition: all 0.2s;
}

.ppv-lang-item:hover {
    background: rgba(255, 0, 128, 0.25);
    color: #fff;
}

        </style>
        <?php
        return ob_get_clean();
    }
}
