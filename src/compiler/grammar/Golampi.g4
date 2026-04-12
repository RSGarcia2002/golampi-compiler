grammar Golampi;

program
    : packageDecl functionDecl* mainFunction functionDecl* EOF
    ;

packageDecl
    : 'package' ('main' | IDENTIFIER)
    ;

mainFunction
    : 'func' 'main' '(' ')' block
    ;

functionDecl
    : 'func' IDENTIFIER '(' ')' block
    ;

block
    : '{' statement* '}'
    ;

statement
    : varDecl ';'
    | assignment ';'
    | printStmt ';'
    | returnStmt ';'
    | ifStmt
    | forStmt
    | breakStmt ';'
    | continueStmt ';'
    | expr ';'
    | block
    ;

ifStmt
    : 'if' expr block ('else' (ifStmt | block))?
    ;

forStmt
    : 'for' expr? block
    ;

breakStmt
    : 'break'
    ;

continueStmt
    : 'continue'
    ;

varDecl
    : 'var' IDENTIFIER typeSpec ('=' expr)?
    ;

typeSpec
    : 'int'
    | 'float'
    | 'bool'
    | 'string'
    ;

assignment
    : IDENTIFIER '=' expr
    ;

printStmt
    : 'fmt' '.' 'Println' '(' argList? ')'
    ;

returnStmt
    : 'return' expr?
    ;

argList
    : expr (',' expr)*
    ;

expr
    : '!' expr                          # unaryExpr
    | '-' expr                          # unaryExpr
    | expr ('*' | '/' | '%') expr       # binaryExpr
    | expr ('+' | '-') expr             # binaryExpr
    | expr ('<' | '<=' | '>' | '>=') expr # binaryExpr
    | expr ('==' | '!=') expr           # binaryExpr
    | expr '&&' expr                    # binaryExpr
    | expr '||' expr                    # binaryExpr
    | '(' expr ')'                      # groupedExpr
    | literal                           # literalExpr
    | IDENTIFIER                        # identifierExpr
    ;

literal
    : INT_LITERAL
    | FLOAT_LITERAL
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
