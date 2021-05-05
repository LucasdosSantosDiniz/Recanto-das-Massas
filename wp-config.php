<?php
/**
 * As configurações básicas do WordPress
 *
 * O script de criação wp-config.php usa esse arquivo durante a instalação.
 * Você não precisa usar o site, você pode copiar este arquivo
 * para "wp-config.php" e preencher os valores.
 *
 * Este arquivo contém as seguintes configurações:
 *
 * * Configurações do MySQL
 * * Chaves secretas
 * * Prefixo do banco de dados
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Configurações do MySQL - Você pode pegar estas informações com o serviço de hospedagem ** //
/** O nome do banco de dados do WordPress */
define( 'DB_NAME', 'cevwp' );

/** Usuário do banco de dados MySQL */
define( 'DB_USER', 'root' );

/** Senha do banco de dados MySQL */
define( 'DB_PASSWORD', '' );

/** Nome do host do MySQL */
define( 'DB_HOST', 'localhost' );

/** Charset do banco de dados a ser usado na criação das tabelas. */
define( 'DB_CHARSET', 'utf8mb4' );

/** O tipo de Collate do banco de dados. Não altere isso se tiver dúvidas. */
define( 'DB_COLLATE', '' );

/*define('WP_SITEURL', 'https://http://85b6da188529.ngrok.io/');
define('WP_HOME', 'https://http://85b6da188529.ngrok.io/');*/


/**#@+
 * Chaves únicas de autenticação e salts.
 *
 * Altere cada chave para um frase única!
 * Você pode gerá-las
 * usando o {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org
 * secret-key service}
 * Você pode alterá-las a qualquer momento para invalidar quaisquer
 * cookies existentes. Isto irá forçar todos os
 * usuários a fazerem login novamente.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         '`y&,q#8$pC*V7Yp%+KsZ$V[+DFwb7iu=sj]gu]Z[Lk*U.J*2IM7{!RDQa^n&J6>h' );
define( 'SECURE_AUTH_KEY',  '}D)rls.A:Jzz)81C1=nkRJ9^4;=XY0FVpG%:&.5a?p:75c^;APUzA=-pf4~b)5B;' );
define( 'LOGGED_IN_KEY',    '&sh^)6UTf^Ic`PY7AGPip{8;PcE>g[n=LqxFIPRPKwE<a/G}sT8D@hlgtePP90y#' );
define( 'NONCE_KEY',        '*g2WmgAyWM/}IL8fFi#{w:/[^ljt~,>yH4l2z[%};o/Di3pBE_1 |ok$CqDf-1?I' );
define( 'AUTH_SALT',        '6b@EFVm1sQ{5eQ~9)QSR5g3*v7>Q1M=a~hWjEv`t(#y0?d4`X@{+Oa_C+zP)QJ7b' );
define( 'SECURE_AUTH_SALT', 'EK(`#8j ?xX>Yz0{K45KXOnJQv?G_{>^5:TQY{@s`#kcYE*dpIM7n:fa_sanO 0i' );
define( 'LOGGED_IN_SALT',   '1gGTJ@=( u:AGP.^2;_k$0qWc~Phh%*W^+lPE<v_*_}hf#E,wvH8T*pYmIZ1.-PC' );
define( 'NONCE_SALT',       'gJ@HLJ;<zmHSwt`hRmVjSH9Kw{#S]D;(/xQ23$L3ef&Ie7edjh]u8D,X[_>W}HT$' );

/**#@-*/

/**
 * Prefixo da tabela do banco de dados do WordPress.
 *
 * Você pode ter várias instalações em um único banco de dados se você der
 * um prefixo único para cada um. Somente números, letras e sublinhados!
 */
$table_prefix = 'wp_cevwp';

/**
 * Para desenvolvedores: Modo de debug do WordPress.
 *
 * Altere isto para true para ativar a exibição de avisos
 * durante o desenvolvimento. É altamente recomendável que os
 * desenvolvedores de plugins e temas usem o WP_DEBUG
 * em seus ambientes de desenvolvimento.
 *
 * Para informações sobre outras constantes que podem ser utilizadas
 * para depuração, visite o Codex.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* Isto é tudo, pode parar de editar! :) */

/** Caminho absoluto para o diretório WordPress. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Configura as variáveis e arquivos do WordPress. */
require_once ABSPATH . 'wp-settings.php';
