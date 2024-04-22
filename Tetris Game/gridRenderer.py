import pygame

class gridRenderer:
    def __init__(self, screen, grid, block_size, colors):
        self.screen = screen
        self.grid = grid
        self.block_size = block_size
        self.colors = colors
        self.grey = (128, 128, 128)  # Default color for empty cells

    def draw_grid(self):
        """Draws the entire grid to the screen."""
        for y, row in enumerate(self.grid):
            for x, cell in enumerate(row):
                color = self.grey if cell == 0 else self.colors[cell-1]
                pygame.draw.rect(self.screen, color, (x * self.block_size, y * self.block_size, self.block_size, self.block_size))

    def draw_tetromino(self, tetromino):
        """Draws the current tetromino to the screen."""
        shape = tetromino.shape[tetromino.rotation]
        for y, row in enumerate(shape):
            for x, cell in enumerate(row):
                if cell:
                    color = tetromino.color
                    pygame.draw.rect(self.screen, color, ((tetromino.position[0] + x) * self.block_size, (tetromino.position[1] + y) * self.block_size, self.block_size, self.block_size))

    def update(self):
        """Updates the entire screen, meant to be called after all drawing operations."""
        pygame.display.update()
