grammar Golampi;

program
    : packageDecl? functionDecl* mainFunction functionDecl* EOF
    ;

packageDecl
    : 'package' ('main' | IDENTIFIER)
    ;

mainFunction
    : 'func' 'main' '(' ')' block
    ;

functionDecl
    : 'func' IDENTIFIER '(' paramList? ')' returnType? block
    ;

paramList
    : param (',' param)*
    ;

param
    : IDENTIFIER typeSpec
    ;

returnType
    : typeSpec
    | '(' typeSpec (',' typeSpec)* ')'
    ;

block
    : '{' statement* '}'
    ;

statement
    : varDecl ';'?
    | constDecl ';'?
    | shortVarDecl ';'?
    | assignment ';'?
    | postStmt ';'?
    | printStmt ';'?
    | returnStmt ';'?
    | ifStmt
    | forStmt
    | switchStmt
    | breakStmt ';'?
    | continueStmt ';'?
    | expr ';'?
    | block
    ;

ifStmt
    : 'if' expr block ('else' (ifStmt | block))?
    ;

forStmt
    : 'for' forClause block
    | 'for' expr block
    | 'for' block
    ;

forClause
    : forInit? ';' expr? ';' forPost?
    ;

forInit
    : shortVarDecl
    | assignment
    | postStmt
    | expr
    ;

forPost
    : postStmt
    | assignment
    | expr
    ;

switchStmt
    : 'switch' expr '{' switchCase* defaultCase? '}'
    ;

switchCase
    : 'case' expr ':' statement*
    ;

defaultCase
    : 'default' ':' statement*
    ;

breakStmt
    : 'break'
    ;

continueStmt
    : 'continue'
    ;

varDecl
    : 'var' identifierList (typeSpec ('=' exprList)? | '=' exprList)
    ;

constDecl
    : 'const' identifierList typeSpec '=' exprList
    ;

shortVarDecl
    : identifierList ':=' exprList
    ;

typeSpec
    : '*' typeSpec
    | '[' INT_LITERAL? ']' typeSpec
    | baseType
    ;

baseType
    : 'int'
    | 'int32'
    | 'float'
    | 'float32'
    | 'bool'
    | 'rune'
    | 'string'
    ;

assignment
    : assignTarget assignOp expr
    ;

assignTarget
    : IDENTIFIER
    | '*' IDENTIFIER
    | IDENTIFIER ('[' expr ']')+
    ;

postStmt
    : IDENTIFIER ('++' | '--')
    ;

assignOp
    : '='
    | '+='
    | '-='
    | '*='
    | '/='
    | '%='
    ;

printStmt
    : 'fmt' '.' 'Println' '(' argList? ')'
    ;

returnStmt
    : 'return' exprList?
    ;

argList
    : expr (',' expr)*
    ;

identifierList
    : IDENTIFIER (',' IDENTIFIER)*
    ;

exprList
    : expr (',' expr)* ','?
    ;

expr
    : '!' expr                          # unaryExpr
    | '-' expr                          # unaryExpr
    | '&' expr                          # unaryExpr
    | '*' expr                          # unaryExpr
    | expr '[' expr ']'                 # indexExpr
    | expr ('*' | '/' | '%') expr       # binaryExpr
    | expr ('+' | '-') expr             # binaryExpr
    | expr ('<' | '<=' | '>' | '>=') expr # binaryExpr
    | expr ('==' | '!=') expr           # binaryExpr
    | expr '&&' expr                    # binaryExpr
    | expr '||' expr                    # binaryExpr
    | '(' expr ')'                      # groupedExpr
    | baseType '(' expr ')'             # castExpr
    | IDENTIFIER '(' argList? ')'       # callExpr
    | '[' INT_LITERAL ']' typeSpec '{' exprList? '}' # typedArrayLiteralExpr
    | '{' exprList? '}'                 # braceArrayLiteralExpr
    | '[' argList? ']'                  # arrayLiteralExpr
    | literal                           # literalExpr
    | IDENTIFIER                        # identifierExpr
    ;

literal
    : INT_LITERAL
    | FLOAT_LITERAL
    | CHAR_LITERAL
    | STRING_LITERAL
    | 'true'
    | 'false'
    | 'nil'
    ;

INT_LITERAL
    : [0-9]+
    ;

FLOAT_LITERAL
    : [0-9]+ '.' [0-9]+
    ;

STRING_LITERAL
    : '"' ( '\\' . | ~["\\] )* '"'
    ;

CHAR_LITERAL
    : '\'' ( '\\' . | ~['\\] ) '\''
    ;

IDENTIFIER
    : [a-zA-Z_][a-zA-Z_0-9]*
    ;

LINE_COMMENT
    : '//' ~[\r\n]* -> skip
    ;

BLOCK_COMMENT
    : '/*' .*? '*/' -> skip
    ;

WS
    : [ \t\r\n]+ -> skip
    ;
