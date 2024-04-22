import pygame
import random
from Tetromino import Tetromino
from collisionDetector import collisionDetector
from lineManager import lineManager
from fallingBlocks import fallingBlocks
from gridRenderer import gridRenderer
import sys

pygame.init()

screen_width, screen_height = 300, 600
grid_size = (10, 20)
block_size = 30
FPS = 30

white = (255, 255, 255)
grey = (128, 128, 128)
colors = [
    (0, 240, 240),
    (0, 0, 240),
    (240, 160, 0),
    (0, 240, 0),
    (160, 0, 240),
    (240, 0, 0),
         ]

clock = pygame.time.Clock()
screen = pygame.display.set_mode((screen_width, screen_height))

tetrominoes = {
    'I': [[1, 1, 1, 1]],
    'O': [[1, 1], [1, 1]],
    'T': [[0, 1, 0], [1, 1, 1]],
    'S': [[0, 1, 1], [1, 1, 0]],
    'Z': [[1, 1, 0], [0, 1, 1]],
    'J': [[1, 0, 0], [1, 1, 1]],
    'L': [[0, 0, 1], [1, 1, 1]],
}

def draw_grid(grid):
    for y, row in enumerate(grid):
        for x, cell in enumerate(row):
            color = grey if cell == 0 else colors[cell-1]
            pygame.draw.rect(screen, color, (x * block_size, y * block_size, block_size, block_size))

def draw_tetromino(tetromino):
    shape = tetromino.shape[tetromino.rotation]
    for y, row in enumerate(shape):
        for x, cell in enumerate(row):
            if cell:
                pygame.draw.rect(screen, tetromino.color, ((tetromino.position[0] + x) * block_size, (tetromino.position[1] + y) * block_size, block_size, block_size))

def main():
    running = True
    grid = [[0] * grid_size[0] for _ in range(grid_size[1])]
    collision_detector = collisionDetector(grid)
    current_tetromino = Tetromino(random.choice(list(tetrominoes.values())), random.choice(colors))
    falling_mechanics = fallingBlocks(grid, collision_detector)
    renderer = gridRenderer(screen, grid, block_size, colors)

    last_time = pygame.time.get_ticks()

    while running:
        current_time = pygame.time.get_ticks()
        delta_time = (current_time - last_time) / 1000.0  # convert milliseconds to seconds
        last_time = current_time

        screen.fill(white)

        for event in pygame.event.get():
            if event.type == pygame.QUIT:
                running = False
            if event.type == pygame.KEYDOWN:
                if event.key == pygame.K_LEFT:
                    current_tetromino.move(-1, 0)
                elif event.key == pygame.K_RIGHT:
                    current_tetromino.move(1, 0)
                elif event.key == pygame.K_DOWN:
                    current_tetromino.move(0, 1)
                elif event.key == pygame.K_UP:
                    current_tetromino.rotate()

        if falling_mechanics.update(current_tetromino, delta_time):
            pass

        renderer.draw_grid()
        renderer.draw_tetromino(current_tetromino)
        renderer.update()

        clock.tick(FPS)

    pygame.quit()
